# Python Speech Evaluation Service

This directory contains the Python speech evaluation service.

At T007-01, only the FastAPI foundation and `GET /health` are implemented. The health endpoint is limited to process liveness for the FastAPI service.

Not implemented at T007-01:

- DB connection
- Azure connection
- `/evaluate`
- `X-Internal-Token` validation
- Audio file processing
- WAV conversion
- STT
- Pronunciation Assessment

## Runtime Settings

The service reads the following environment variables:

| Name | Default | Purpose |
| --- | --- | --- |
| `PYTHON_SERVICE_NAME` | `speech-evaluation` | Service name returned by `/health` |
| `PYTHON_SERVICE_HOST` | `127.0.0.1` | Local development host setting |
| `PYTHON_SERVICE_PORT` | `8100` | Temporary development port |

Port `8100` is a temporary default. It is not a final fixed port before OI-002 is resolved. Change `PYTHON_SERVICE_PORT` when checking another port.

## Local PowerShell Check With Docker

Run this from PowerShell with Docker Desktop running:

```powershell
cd C:\Projects\nihongo
docker run --rm -it -p 8100:8100 -v ${PWD}\python:/app -w /app python:3.12-slim sh -c "pip install -r requirements.txt && uvicorn app.main:app --host 0.0.0.0 --port 8100"
```

In another PowerShell window:

```powershell
curl http://localhost:8100/health
```

Expected response:

```json
{
  "status": "ok",
  "service": "speech-evaluation"
}
```

## Port Change Check

Run:

```powershell
cd C:\Projects\nihongo
docker run --rm -it -p 8101:8101 -e PYTHON_SERVICE_PORT=8101 -v ${PWD}\python:/app -w /app python:3.12-slim sh -c "pip install -r requirements.txt && uvicorn app.main:app --host 0.0.0.0 --port 8101"
```

In another PowerShell window:

```powershell
curl http://localhost:8101/health
```

Dockerfile and compose files are intentionally not created in T007-01. Docker and VPS environment setup should be handled by later tasks.

