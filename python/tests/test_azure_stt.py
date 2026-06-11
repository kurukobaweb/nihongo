from types import SimpleNamespace

import pytest

from app.services import azure_stt
from app.services.azure_stt import (
    AzureSttConfigError,
    AzureSttNoMatchError,
    AzureSttTimeoutError,
    build_speech_rate,
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
    ) -> None:
        self.reason = reason
        self.text = text
        self.duration = duration
        self.offset = offset
        self.properties = _Properties(raw_json)


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


class _TimeoutRecognizer(_BaseRecognizer):
    def start_continuous_recognition(self) -> None:
        return None


class _SpeechSdk:
    SpeechConfig = _SpeechConfig
    PropertyId = SimpleNamespace(SpeechServiceResponse_JsonResult="json")
    audio = SimpleNamespace(AudioConfig=_AudioConfig)

    def __init__(self, recognizer_class) -> None:
        self.SpeechRecognizer = recognizer_class


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

    with pytest.raises(AzureSttConfigError):
        transcribe_wav_bytes(b"wav bytes", settings=_settings(key=None))


def test_transcribe_wav_bytes_raises_no_match(monkeypatch) -> None:
    monkeypatch.setattr(azure_stt, "speechsdk", _SpeechSdk(_NoMatchRecognizer))

    with pytest.raises(AzureSttNoMatchError):
        transcribe_wav_bytes(b"wav bytes", settings=_settings())


def test_transcribe_wav_bytes_raises_timeout(monkeypatch) -> None:
    monkeypatch.setattr(azure_stt, "speechsdk", _SpeechSdk(_TimeoutRecognizer))

    with pytest.raises(AzureSttTimeoutError):
        transcribe_wav_bytes(
            b"wav bytes",
            settings=_settings(),
            timeout_seconds=0,
        )


def test_build_speech_rate_requires_positive_recognized_duration() -> None:
    assert build_speech_rate("abc", None) == {"characters_per_minute": None}
    assert build_speech_rate("abc", 0) == {"characters_per_minute": None}
    assert build_speech_rate("abc", 30) == {"characters_per_minute": 6.0}
