from fastapi.testclient import TestClient

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


def _fail_if_audio_conversion_is_called(monkeypatch) -> None:
    def fake_convert(upload_file):
        raise AssertionError("audio conversion should not be called")

    monkeypatch.setattr("app.routes.evaluate.convert_upload_to_wav", fake_convert)


def test_evaluate_accepts_valid_internal_token(monkeypatch) -> None:
    monkeypatch.setenv("SPEECH_SERVICE_INTERNAL_TOKEN", TEST_TOKEN)
    calls = _mock_audio_conversion(monkeypatch)
    client = TestClient(app)

    response = client.post(
        "/evaluate",
        data=_valid_payload(),
        files=_valid_files(),
        headers={"X-Internal-Token": TEST_TOKEN},
    )

    assert response.status_code == 200
    assert response.json() == {
        "status": "accepted",
        "submission_id": SUBMISSION_ID,
    }
    assert calls == ["sample.webm"]


def test_evaluate_rejects_missing_internal_token(monkeypatch) -> None:
    monkeypatch.setenv("SPEECH_SERVICE_INTERNAL_TOKEN", TEST_TOKEN)
    _fail_if_audio_conversion_is_called(monkeypatch)
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
    client = TestClient(app)

    response = client.post(
        "/evaluate",
        data=_valid_payload(),
        files=_valid_files(),
        headers={"X-Internal-Token": TEST_TOKEN},
    )

    assert response.status_code == 422
    assert response.json() == {"detail": "Audio conversion failed"}


def test_evaluate_returns_500_when_ffmpeg_is_missing(monkeypatch) -> None:
    monkeypatch.setenv("SPEECH_SERVICE_INTERNAL_TOKEN", TEST_TOKEN)
    _mock_audio_conversion(monkeypatch, FfmpegNotFoundError("missing ffmpeg"))
    client = TestClient(app)

    response = client.post(
        "/evaluate",
        data=_valid_payload(),
        files=_valid_files(),
        headers={"X-Internal-Token": TEST_TOKEN},
    )

    assert response.status_code == 500
    assert response.json() == {
        "detail": "Audio conversion dependency is not available",
    }
