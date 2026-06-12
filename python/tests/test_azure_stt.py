from types import SimpleNamespace

import pytest

from app.services import azure_stt
from app.services.azure_stt import (
    AzureSttCanceledError,
    AzureSttConfigError,
    AzureSttNoMatchError,
    AzureSttServiceUnavailableError,
    AzureSttTimeoutError,
    build_speech_rate,
    sanitize_diagnostic_message,
    transcribe_wav_bytes,
)
from app.settings import Settings


def _settings(
    key: str | None = "test-key",
    region: str | None = "japaneast",
    endpoint: str | None = None,
) -> Settings:
    return Settings(
        service_name="speech-evaluation",
        host="127.0.0.1",
        port=8100,
        internal_token="test-internal-token",
        azure_speech_key=key,
        azure_speech_region=region,
        azure_speech_endpoint=endpoint,
    )


class _Signal:
    def __init__(self) -> None:
        self.handlers = []

    def connect(self, handler) -> None:
        self.handlers.append(handler)

    def emit(self, event) -> None:
        for handler in self.handlers:
            handler(event)


class _Properties:
    def __init__(self, raw_json: str) -> None:
        self.raw_json = raw_json

    def get_property(self, property_id) -> str:
        return self.raw_json


class _Result:
    def __init__(
        self,
        reason: str,
        text: str = "",
        duration: int = 0,
        offset: int = 0,
        raw_json: str = "",
        cancellation_reason: str | None = None,
        cancellation_error_code: str | None = None,
        error_details: str | None = None,
    ) -> None:
        self.reason = reason
        self.text = text
        self.duration = duration
        self.offset = offset
        self.properties = _Properties(raw_json)
        self.cancellation_reason = cancellation_reason
        self.cancellation_error_code = cancellation_error_code
        self.error_details = error_details


class _SpeechConfig:
    calls = []

    def __init__(self, subscription, region=None, endpoint=None) -> None:
        self.subscription = subscription
        self.region = region
        self.endpoint = endpoint
        self.speech_recognition_language = None
        self.calls.append(self)


class _AudioConfig:
    def __init__(self, filename) -> None:
        self.filename = filename


class _BaseRecognizer:
    def __init__(self, speech_config, audio_config) -> None:
        self.speech_config = speech_config
        self.audio_config = audio_config
        self.recognized = _Signal()
        self.canceled = _Signal()
        self.session_started = _Signal()
        self.session_stopped = _Signal()
        self.stopped = False

    def stop_continuous_recognition(self) -> None:
        self.stopped = True


class _SuccessfulRecognizer(_BaseRecognizer):
    def start_continuous_recognition(self) -> None:
        self.session_started.emit(SimpleNamespace(session_id="session-1"))
        self.recognized.emit(
            SimpleNamespace(
                result=_Result(
                    reason="RecognizedSpeech",
                    text="abcde",
                    duration=30_000_000,
                    offset=10_000_000,
                    raw_json='{"Id":"request-1","RecognitionStatus":"Success"}',
                ),
            ),
        )
        self.session_stopped.emit(SimpleNamespace())


class _NoMatchRecognizer(_BaseRecognizer):
    def start_continuous_recognition(self) -> None:
        self.recognized.emit(SimpleNamespace(result=_Result(reason="NoMatch")))
        self.session_stopped.emit(SimpleNamespace())


class _CanceledRecognizer(_BaseRecognizer):
    def start_continuous_recognition(self) -> None:
        self.session_started.emit(SimpleNamespace(session_id="session-1"))
        self.canceled.emit(
            SimpleNamespace(
                result=_Result(
                    reason="Canceled",
                    raw_json='{"Id":"request-2"}',
                    cancellation_reason="Error",
                    cancellation_error_code="AuthenticationFailure",
                    error_details=(
                        "failure for https://secret.example/speech "
                        "with key=abcdefghijklmnopqrstuvwxyz123456"
                    ),
                ),
            ),
        )


class _CanceledWithoutDetailsRecognizer(_BaseRecognizer):
    def start_continuous_recognition(self) -> None:
        self.canceled.emit(
            SimpleNamespace(
                result=_Result(
                    reason="Canceled",
                    cancellation_reason="Canceled",
                ),
            ),
        )


class _TimeoutRecognizer(_BaseRecognizer):
    def start_continuous_recognition(self) -> None:
        return None


class _ExceptionRecognizer(_BaseRecognizer):
    def start_continuous_recognition(self) -> None:
        raise RuntimeError("connect failed at https://secret.example/speech")


class _CancellationDetails:
    def __init__(self, result) -> None:
        self.result = result

    @property
    def reason(self):
        return self.result.cancellation_reason

    @property
    def error_code(self):
        return self.result.cancellation_error_code

    @property
    def error_details(self):
        return self.result.error_details


class _FailingCancellationDetails:
    def __init__(self, result) -> None:
        raise RuntimeError(
            "details failed at https://secret.example/speech "
            "with token abcdefghijklmnopqrstuvwxyz123456"
        )


class _SpeechSdk:
    SpeechConfig = _SpeechConfig
    CancellationDetails = _CancellationDetails
    PropertyId = SimpleNamespace(SpeechServiceResponse_JsonResult="json")
    audio = SimpleNamespace(AudioConfig=_AudioConfig)

    def __init__(self, recognizer_class) -> None:
        self.SpeechRecognizer = recognizer_class


class _SpeechSdkWithoutCancellationDetails(_SpeechSdk):
    CancellationDetails = None


def test_transcribe_wav_bytes_uses_endpoint_first_and_returns_result(monkeypatch) -> None:
    _SpeechConfig.calls = []
    monkeypatch.setattr(azure_stt, "speechsdk", _SpeechSdk(_SuccessfulRecognizer))

    result = transcribe_wav_bytes(
        b"wav bytes",
        settings=_settings(endpoint="https://example.invalid/speech"),
    )

    assert result.transcript == "abcde"
    assert result.audio_duration_seconds is None
    assert result.recognized_duration_seconds == 3.0
    assert result.azure_request_id == "request-1"
    assert result.azure_session_id == "session-1"
    assert result.raw_azure_response["recognition_mode"] == "continuous"
    assert _SpeechConfig.calls[-1].endpoint == "https://example.invalid/speech"
    assert _SpeechConfig.calls[-1].region is None
    assert _SpeechConfig.calls[-1].speech_recognition_language == "ja-JP"


def test_transcribe_wav_bytes_requires_key(monkeypatch) -> None:
    monkeypatch.setattr(azure_stt, "speechsdk", _SpeechSdk(_SuccessfulRecognizer))

    with pytest.raises(AzureSttConfigError) as exc_info:
        transcribe_wav_bytes(b"wav bytes", settings=_settings(key=None))

    assert exc_info.value.diagnostic["category"] == "config_error"
    assert exc_info.value.diagnostic["config"]["key_configured"] is False


def test_transcribe_wav_bytes_raises_no_match(monkeypatch) -> None:
    monkeypatch.setattr(azure_stt, "speechsdk", _SpeechSdk(_NoMatchRecognizer))

    with pytest.raises(AzureSttNoMatchError) as exc_info:
        transcribe_wav_bytes(b"wav bytes", settings=_settings())

    assert exc_info.value.diagnostic["category"] == "no_match"
    assert exc_info.value.diagnostic["result_reason"] == "NoMatch"


def test_transcribe_wav_bytes_raises_canceled_with_sanitized_diagnostic(
    monkeypatch,
) -> None:
    monkeypatch.setattr(azure_stt, "speechsdk", _SpeechSdk(_CanceledRecognizer))

    with pytest.raises(AzureSttCanceledError) as exc_info:
        transcribe_wav_bytes(b"wav bytes", settings=_settings())

    diagnostic = exc_info.value.diagnostic
    assert diagnostic["category"] == "azure_canceled"
    assert diagnostic["result_reason"] == "Canceled"
    assert diagnostic["cancellation_reason"] == "Error"
    assert diagnostic["cancellation_error_code"] == "AuthenticationFailure"
    assert diagnostic["cancellation_error_code_available"] is True
    assert diagnostic["error_details_available"] is True
    assert diagnostic["cancellation_details_source"] == "sdk_cancellation_details"
    assert diagnostic["azure_request_id"] == "request-2"
    assert diagnostic["azure_session_id"] == "session-1"
    assert "secret.example" not in diagnostic["error_details"]
    assert "abcdefghijklmnopqrstuvwxyz123456" not in diagnostic["error_details"]


def test_transcribe_wav_bytes_does_not_require_from_result(monkeypatch) -> None:
    speech_sdk = _SpeechSdk(_CanceledRecognizer)
    speech_sdk.CancellationDetails = _CancellationDetails
    assert not hasattr(speech_sdk.CancellationDetails, "from_result")
    monkeypatch.setattr(azure_stt, "speechsdk", speech_sdk)

    with pytest.raises(AzureSttCanceledError) as exc_info:
        transcribe_wav_bytes(b"wav bytes", settings=_settings())

    assert exc_info.value.diagnostic["cancellation_details_source"] == (
        "sdk_cancellation_details"
    )
    assert exc_info.value.diagnostic["cancellation_error_code"] == (
        "AuthenticationFailure"
    )


def test_transcribe_wav_bytes_keeps_missing_cancellation_fields(
    monkeypatch,
) -> None:
    monkeypatch.setattr(
        azure_stt,
        "speechsdk",
        _SpeechSdk(_CanceledWithoutDetailsRecognizer),
    )

    with pytest.raises(AzureSttCanceledError) as exc_info:
        transcribe_wav_bytes(b"wav bytes", settings=_settings())

    diagnostic = exc_info.value.diagnostic
    assert "cancellation_error_code" in diagnostic
    assert "error_details" in diagnostic
    assert diagnostic["cancellation_error_code"] is None
    assert diagnostic["error_details"] is None
    assert diagnostic["cancellation_error_code_available"] is False
    assert diagnostic["error_details_available"] is False
    assert diagnostic["cancellation_details_source"] == "sdk_cancellation_details"


def test_transcribe_wav_bytes_reports_cancellation_details_fallback(
    monkeypatch,
) -> None:
    speech_sdk = _SpeechSdk(_CanceledRecognizer)
    speech_sdk.CancellationDetails = _FailingCancellationDetails
    monkeypatch.setattr(azure_stt, "speechsdk", speech_sdk)

    with pytest.raises(AzureSttCanceledError) as exc_info:
        transcribe_wav_bytes(b"wav bytes", settings=_settings())

    diagnostic = exc_info.value.diagnostic
    assert diagnostic["cancellation_details_source"] == "failed"
    assert "secret.example" not in diagnostic["cancellation_details_error"]
    assert "abcdefghijklmnopqrstuvwxyz123456" not in diagnostic[
        "cancellation_details_error"
    ]


def test_transcribe_wav_bytes_reports_result_fallback_when_sdk_details_missing(
    monkeypatch,
) -> None:
    monkeypatch.setattr(
        azure_stt,
        "speechsdk",
        _SpeechSdkWithoutCancellationDetails(_CanceledRecognizer),
    )

    with pytest.raises(AzureSttCanceledError) as exc_info:
        transcribe_wav_bytes(b"wav bytes", settings=_settings())

    diagnostic = exc_info.value.diagnostic
    assert diagnostic["cancellation_details_source"] == "result_fallback"
    assert diagnostic["cancellation_error_code"] is None
    assert diagnostic["error_details"] is None
    assert diagnostic["cancellation_error_code_available"] is False
    assert diagnostic["error_details_available"] is False


def test_transcribe_wav_bytes_raises_sdk_exception_with_diagnostic(
    monkeypatch,
) -> None:
    monkeypatch.setattr(azure_stt, "speechsdk", _SpeechSdk(_ExceptionRecognizer))

    with pytest.raises(AzureSttServiceUnavailableError) as exc_info:
        transcribe_wav_bytes(b"wav bytes", settings=_settings())

    diagnostic = exc_info.value.diagnostic
    assert diagnostic["category"] == "sdk_exception"
    assert diagnostic["exception_type"] == "RuntimeError"
    assert "secret.example" not in diagnostic["message"]


def test_transcribe_wav_bytes_raises_timeout(monkeypatch) -> None:
    monkeypatch.setattr(azure_stt, "speechsdk", _SpeechSdk(_TimeoutRecognizer))

    with pytest.raises(AzureSttTimeoutError) as exc_info:
        transcribe_wav_bytes(
            b"wav bytes",
            settings=_settings(),
            timeout_seconds=0,
        )

    assert exc_info.value.diagnostic["category"] == "azure_timeout"


def test_build_speech_rate_requires_positive_recognized_duration() -> None:
    assert build_speech_rate("abc", None) == {"characters_per_minute": None}
    assert build_speech_rate("abc", 0) == {"characters_per_minute": None}
    assert build_speech_rate("abc", 30) == {"characters_per_minute": 6.0}


def test_sanitize_diagnostic_message_redacts_sensitive_values() -> None:
    message = (
        "line1\n"
        "https://secret.example/speech?region=japaneast "
        "subscription-key=abcdefghijklmnopqrstuvwxyz123456"
    )

    sanitized = sanitize_diagnostic_message(message)

    assert "\n" not in sanitized
    assert "secret.example" not in sanitized
    assert "abcdefghijklmnopqrstuvwxyz123456" not in sanitized
    assert "[redacted_url]" in sanitized
