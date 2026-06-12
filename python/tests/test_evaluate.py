from fastapi.testclient import TestClient

from app.services.azure_stt import (
    AzureSttCanceledError,
    AzureSttConfigError,
    AzureSttNoMatchError,
    AzureSttResult,
    AzureSttServiceUnavailableError,
    AzureSttTimeoutError,
)
from app.services.audio_conversion import FfmpegNotFoundError, InvalidAudioFileError
from app.main import app


TEST_TOKEN = "test-internal-token"
SUBMISSION_ID = "00000000-0000-0000-0000-000000000001"


def _valid_payload() -> dict[str, str]:
    return {
        "submission_id": SUBMISSION_ID,
        "question_id": "1",
        "expected_duration": "60",
        "feature_flags": '{"pronunciation":true,"fluency":true}',
    }


def _valid_files() -> dict[str, tuple[str, bytes, str]]:
    return {
        "audio_file": ("sample.webm", b"dummy audio bytes", "audio/webm"),
    }


def _mock_audio_conversion(monkeypatch, exception: Exception | None = None) -> list[str]:
    calls: list[str] = []

    def fake_convert(upload_file):
        calls.append(upload_file.filename)
        if exception is not None:
            raise exception
        return b"wav bytes"

    monkeypatch.setattr("app.routes.evaluate.convert_upload_to_wav", fake_convert)
    return calls


def _mock_azure_stt(
    monkeypatch,
    result: AzureSttResult | None = None,
    exception: Exception | None = None,
) -> list[bytes]:
    calls: list[bytes] = []

    def fake_transcribe(wav_bytes):
        calls.append(wav_bytes)
        if exception is not None:
            raise exception
        return result or AzureSttResult(
            transcript="abcde",
            audio_duration_seconds=None,
            recognized_duration_seconds=3.0,
            azure_request_id="request-1",
            azure_session_id="session-1",
            raw_azure_response={"recognition_mode": "continuous"},
        )

    monkeypatch.setattr("app.routes.evaluate.transcribe_wav_bytes", fake_transcribe)
    return calls


def _fail_if_audio_conversion_is_called(monkeypatch) -> None:
    def fake_convert(upload_file):
        raise AssertionError("audio conversion should not be called")

    monkeypatch.setattr("app.routes.evaluate.convert_upload_to_wav", fake_convert)


def _fail_if_azure_stt_is_called(monkeypatch) -> None:
    def fake_transcribe(wav_bytes):
        raise AssertionError("Azure STT should not be called")

    monkeypatch.setattr("app.routes.evaluate.transcribe_wav_bytes", fake_transcribe)


def test_evaluate_returns_stt_result_for_valid_internal_token(monkeypatch) -> None:
    monkeypatch.setenv("SPEECH_SERVICE_INTERNAL_TOKEN", TEST_TOKEN)
    audio_calls = _mock_audio_conversion(monkeypatch)
    stt_calls = _mock_azure_stt(monkeypatch)
    client = TestClient(app)

    response = client.post(
        "/evaluate",
        data=_valid_payload(),
        files=_valid_files(),
        headers={"X-Internal-Token": TEST_TOKEN},
    )

    assert response.status_code == 200
    assert response.json() == {
        "status": "success",
        "submission_id": SUBMISSION_ID,
        "transcript": "abcde",
        "audio_duration_seconds": None,
        "recognized_duration_seconds": 3.0,
        "speech_rate": {"characters_per_minute": 100.0},
        "azure_request_id": "request-1",
        "azure_session_id": "session-1",
        "raw_azure_response": {"recognition_mode": "continuous"},
    }
    assert audio_calls == ["sample.webm"]
    assert stt_calls == [b"wav bytes"]


def test_evaluate_omits_speech_rate_when_recognized_duration_is_null(
    monkeypatch,
) -> None:
    monkeypatch.setenv("SPEECH_SERVICE_INTERNAL_TOKEN", TEST_TOKEN)
    _mock_audio_conversion(monkeypatch)
    _mock_azure_stt(
        monkeypatch,
        AzureSttResult(
            transcript="abcde",
            audio_duration_seconds=None,
            recognized_duration_seconds=None,
            azure_request_id=None,
            azure_session_id=None,
            raw_azure_response={},
        ),
    )
    client = TestClient(app)

    response = client.post(
        "/evaluate",
        data=_valid_payload(),
        files=_valid_files(),
        headers={"X-Internal-Token": TEST_TOKEN},
    )

    assert response.status_code == 200
    assert response.json()["speech_rate"] == {"characters_per_minute": None}


def test_evaluate_rejects_missing_internal_token(monkeypatch) -> None:
    monkeypatch.setenv("SPEECH_SERVICE_INTERNAL_TOKEN", TEST_TOKEN)
    _fail_if_audio_conversion_is_called(monkeypatch)
    _fail_if_azure_stt_is_called(monkeypatch)
    client = TestClient(app)

    response = client.post(
        "/evaluate",
        data=_valid_payload(),
        files=_valid_files(),
    )

    assert response.status_code == 401


def test_evaluate_rejects_invalid_internal_token(monkeypatch) -> None:
    monkeypatch.setenv("SPEECH_SERVICE_INTERNAL_TOKEN", TEST_TOKEN)
    _fail_if_audio_conversion_is_called(monkeypatch)
    _fail_if_azure_stt_is_called(monkeypatch)
    client = TestClient(app)

    response = client.post(
        "/evaluate",
        data=_valid_payload(),
        files=_valid_files(),
        headers={"X-Internal-Token": "wrong-token"},
    )

    assert response.status_code == 401


def test_evaluate_rejects_missing_required_form_field(monkeypatch) -> None:
    monkeypatch.setenv("SPEECH_SERVICE_INTERNAL_TOKEN", TEST_TOKEN)
    _fail_if_audio_conversion_is_called(monkeypatch)
    _fail_if_azure_stt_is_called(monkeypatch)
    client = TestClient(app)
    payload = _valid_payload()
    payload.pop("submission_id")

    response = client.post(
        "/evaluate",
        data=payload,
        files=_valid_files(),
        headers={"X-Internal-Token": TEST_TOKEN},
    )

    assert response.status_code == 422


def test_evaluate_rejects_missing_audio_file(monkeypatch) -> None:
    monkeypatch.setenv("SPEECH_SERVICE_INTERNAL_TOKEN", TEST_TOKEN)
    _fail_if_audio_conversion_is_called(monkeypatch)
    _fail_if_azure_stt_is_called(monkeypatch)
    client = TestClient(app)

    response = client.post(
        "/evaluate",
        data=_valid_payload(),
        headers={"X-Internal-Token": TEST_TOKEN},
    )

    assert response.status_code == 422


def test_evaluate_returns_422_when_audio_conversion_fails(monkeypatch) -> None:
    monkeypatch.setenv("SPEECH_SERVICE_INTERNAL_TOKEN", TEST_TOKEN)
    _mock_audio_conversion(monkeypatch, InvalidAudioFileError("bad audio"))
    _fail_if_azure_stt_is_called(monkeypatch)
    client = TestClient(app)

    response = client.post(
        "/evaluate",
        data=_valid_payload(),
        files=_valid_files(),
        headers={"X-Internal-Token": TEST_TOKEN},
    )

    assert response.status_code == 422
    assert response.json() == {
        "status": "error",
        "error_type": "audio_conversion_failed",
        "detail": "Audio conversion failed",
        "retryable": False,
        "user_action": "rerecord",
    }
    assert "transcript" not in response.json()
    assert "speech_rate" not in response.json()


def test_evaluate_returns_500_when_ffmpeg_is_missing(monkeypatch) -> None:
    monkeypatch.setenv("SPEECH_SERVICE_INTERNAL_TOKEN", TEST_TOKEN)
    _mock_audio_conversion(monkeypatch, FfmpegNotFoundError("missing ffmpeg"))
    _fail_if_azure_stt_is_called(monkeypatch)
    client = TestClient(app)

    response = client.post(
        "/evaluate",
        data=_valid_payload(),
        files=_valid_files(),
        headers={"X-Internal-Token": TEST_TOKEN},
    )

    assert response.status_code == 500
    assert response.json() == {
        "status": "error",
        "error_type": "audio_conversion_dependency_missing",
        "detail": "Audio conversion dependency is not available",
        "retryable": False,
        "user_action": "contact_admin",
    }


def test_evaluate_returns_422_when_speech_is_not_recognized(monkeypatch) -> None:
    monkeypatch.setenv("SPEECH_SERVICE_INTERNAL_TOKEN", TEST_TOKEN)
    _mock_audio_conversion(monkeypatch)
    _mock_azure_stt(
        monkeypatch,
        exception=AzureSttNoMatchError(
            "no speech",
            diagnostic={
                "category": "no_match",
                "result_reason": "NoMatch",
            },
        ),
    )
    client = TestClient(app)

    response = client.post(
        "/evaluate",
        data=_valid_payload(),
        files=_valid_files(),
        headers={"X-Internal-Token": TEST_TOKEN},
    )

    assert response.status_code == 422
    assert response.json() == {
        "status": "error",
        "error_type": "speech_unrecognized",
        "detail": "Speech could not be recognized",
        "retryable": False,
        "user_action": "rerecord",
        "diagnostic": {
            "category": "no_match",
            "result_reason": "NoMatch",
        },
    }
    assert "transcript" not in response.json()
    assert "speech_rate" not in response.json()


def test_evaluate_returns_422_for_end_of_stream_without_transcript(
    monkeypatch,
) -> None:
    monkeypatch.setenv("SPEECH_SERVICE_INTERNAL_TOKEN", TEST_TOKEN)
    _mock_audio_conversion(monkeypatch)
    _mock_azure_stt(
        monkeypatch,
        exception=AzureSttNoMatchError(
            "end of stream",
            diagnostic={
                "category": "speech_unrecognized",
                "result_reason": "Canceled",
                "cancellation_reason": "EndOfStream",
                "cancellation_error_code": None,
                "cancellation_error_code_available": False,
                "error_details": "",
                "error_details_available": False,
                "cancellation_details_source": "sdk_cancellation_details",
                "transcript_available": False,
                "recognized_text_length": 0,
                "end_of_stream_handling": "unrecognized_speech",
            },
        ),
    )
    client = TestClient(app)

    response = client.post(
        "/evaluate",
        data=_valid_payload(),
        files=_valid_files(),
        headers={"X-Internal-Token": TEST_TOKEN},
    )

    assert response.status_code == 422
    body = response.json()
    assert body["status"] == "error"
    assert body["error_type"] == "speech_unrecognized"
    assert body["detail"] == "Speech could not be recognized"
    assert body["retryable"] is False
    assert body["user_action"] == "rerecord"
    assert "transcript" not in body
    assert "speech_rate" not in body
    diagnostic = body["diagnostic"]
    assert diagnostic["category"] == "speech_unrecognized"
    assert diagnostic["cancellation_reason"] == "EndOfStream"
    assert diagnostic["cancellation_error_code"] is None
    assert diagnostic["error_details"] == ""


def test_evaluate_returns_diagnostic_when_azure_recognition_is_canceled(
    monkeypatch,
) -> None:
    monkeypatch.setenv("SPEECH_SERVICE_INTERNAL_TOKEN", TEST_TOKEN)
    _mock_audio_conversion(monkeypatch)
    _mock_azure_stt(
        monkeypatch,
        exception=AzureSttCanceledError(
            "canceled",
            diagnostic={
                "category": "azure_canceled",
                "result_reason": "Canceled",
                "cancellation_reason": "Error",
                "cancellation_error_code": "AuthenticationFailure",
                "cancellation_error_code_available": True,
                "error_details": "safe diagnostic",
                "error_details_available": True,
                "cancellation_details_source": "sdk_cancellation_details",
            },
        ),
    )
    client = TestClient(app)

    response = client.post(
        "/evaluate",
        data=_valid_payload(),
        files=_valid_files(),
        headers={"X-Internal-Token": TEST_TOKEN},
    )

    assert response.status_code == 503
    assert response.json() == {
        "status": "error",
        "error_type": "azure_canceled",
        "detail": "Azure Speech recognition was canceled",
        "retryable": True,
        "user_action": "retry_later",
        "diagnostic": {
            "category": "azure_canceled",
            "result_reason": "Canceled",
            "cancellation_reason": "Error",
            "cancellation_error_code": "AuthenticationFailure",
            "cancellation_error_code_available": True,
            "error_details": "safe diagnostic",
            "error_details_available": True,
            "cancellation_details_source": "sdk_cancellation_details",
        },
    }


def test_evaluate_returns_500_when_azure_configuration_is_missing(
    monkeypatch,
) -> None:
    monkeypatch.setenv("SPEECH_SERVICE_INTERNAL_TOKEN", TEST_TOKEN)
    _mock_audio_conversion(monkeypatch)
    _mock_azure_stt(
        monkeypatch,
        exception=AzureSttConfigError(
            "missing key",
            diagnostic={
                "category": "config_error",
                "config": {
                    "key_configured": False,
                    "region_configured": True,
                    "endpoint_configured": False,
                    "config_mode": "key_region",
                },
            },
        ),
    )
    client = TestClient(app)

    response = client.post(
        "/evaluate",
        data=_valid_payload(),
        files=_valid_files(),
        headers={"X-Internal-Token": TEST_TOKEN},
    )

    assert response.status_code == 500
    assert response.json() == {
        "status": "error",
        "error_type": "azure_configuration_unavailable",
        "detail": "Azure Speech configuration is not available",
        "retryable": False,
        "user_action": "contact_admin",
        "diagnostic": {
            "category": "config_error",
            "config": {
                "key_configured": False,
                "region_configured": True,
                "endpoint_configured": False,
                "config_mode": "key_region",
            },
        },
    }


def test_evaluate_returns_503_when_azure_is_unavailable(monkeypatch) -> None:
    monkeypatch.setenv("SPEECH_SERVICE_INTERNAL_TOKEN", TEST_TOKEN)
    _mock_audio_conversion(monkeypatch)
    _mock_azure_stt(
        monkeypatch,
        exception=AzureSttServiceUnavailableError(
            "timeout",
            diagnostic={
                "category": "sdk_exception",
                "exception_type": "RuntimeError",
                "message": "safe message",
            },
        ),
    )
    client = TestClient(app)

    response = client.post(
        "/evaluate",
        data=_valid_payload(),
        files=_valid_files(),
        headers={"X-Internal-Token": TEST_TOKEN},
    )

    assert response.status_code == 503
    assert response.json() == {
        "status": "error",
        "error_type": "azure_service_unavailable",
        "detail": "Azure Speech SDK error",
        "retryable": True,
        "user_action": "retry_later",
        "diagnostic": {
            "category": "sdk_exception",
            "exception_type": "RuntimeError",
            "message": "safe message",
        },
    }


def test_evaluate_returns_503_when_azure_times_out(monkeypatch) -> None:
    monkeypatch.setenv("SPEECH_SERVICE_INTERNAL_TOKEN", TEST_TOKEN)
    _mock_audio_conversion(monkeypatch)
    _mock_azure_stt(
        monkeypatch,
        exception=AzureSttTimeoutError(
            "timeout",
            diagnostic={
                "category": "azure_timeout",
                "message": "Continuous recognition timed out",
            },
        ),
    )
    client = TestClient(app)

    response = client.post(
        "/evaluate",
        data=_valid_payload(),
        files=_valid_files(),
        headers={"X-Internal-Token": TEST_TOKEN},
    )

    assert response.status_code == 503
    assert response.json() == {
        "status": "error",
        "error_type": "azure_service_unavailable",
        "detail": "Azure Speech SDK error",
        "retryable": True,
        "user_action": "retry_later",
        "diagnostic": {
            "category": "azure_timeout",
            "message": "Continuous recognition timed out",
        },
    }
