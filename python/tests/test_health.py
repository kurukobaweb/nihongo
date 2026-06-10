from fastapi.testclient import TestClient

from app.main import app


def test_health_returns_process_status() -> None:
    client = TestClient(app)

    response = client.get("/health")

    assert response.status_code == 200
    assert response.json() == {
        "status": "ok",
        "service": "speech-evaluation",
    }


def test_health_uses_service_name_environment_variable(monkeypatch) -> None:
    monkeypatch.setenv("PYTHON_SERVICE_NAME", "speech-evaluation-test")
    client = TestClient(app)

    response = client.get("/health")

    assert response.status_code == 200
    assert response.json()["service"] == "speech-evaluation-test"

