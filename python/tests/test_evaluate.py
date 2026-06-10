from fastapi.testclient import TestClient

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


def test_evaluate_accepts_valid_internal_token(monkeypatch) -> None:
    monkeypatch.setenv("SPEECH_SERVICE_INTERNAL_TOKEN", TEST_TOKEN)
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


def test_evaluate_rejects_missing_internal_token(monkeypatch) -> None:
    monkeypatch.setenv("SPEECH_SERVICE_INTERNAL_TOKEN", TEST_TOKEN)
    client = TestClient(app)

    response = client.post(
        "/evaluate",
        data=_valid_payload(),
        files=_valid_files(),
    )

    assert response.status_code == 401


def test_evaluate_rejects_invalid_internal_token(monkeypatch) -> None:
    monkeypatch.setenv("SPEECH_SERVICE_INTERNAL_TOKEN", TEST_TOKEN)
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
    client = TestClient(app)

    response = client.post(
        "/evaluate",
        data=_valid_payload(),
        headers={"X-Internal-Token": TEST_TOKEN},
    )

    assert response.status_code == 422
