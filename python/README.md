# Python Speech Evaluation Service

This directory contains the Python speech evaluation service.

At T007-03, the FastAPI foundation includes `GET /health` and the internal `POST /evaluate` endpoint. The health endpoint is limited to process liveness for the FastAPI service.

`POST /evaluate` converts uploaded WebM/Opus audio to temporary WAV bytes before returning the accepted response. The generated WAV is not persisted and is discarded in T007-03.

Not implemented after T007-03:

- DB connection
- Azure connection
- STT
- Pronunciation Assessment
- Evaluation result persistence

## Audio Conversion

T007-03 adds WebM/Opus to WAV conversion for the uploaded `audio_file`. The service calls the system `ffmpeg` binary with `subprocess.run()` and does not use Python wrapper packages such as pydub, moviepy, or av.

The conversion output format is:

- PCM WAV
- 16 kHz
- 16-bit signed samples
- mono

Uploaded input and generated WAV output are stored only in temporary files during conversion. They are deleted after success or failure. The generated WAV bytes are discarded after conversion in T007-03; later tasks own Azure STT, Pronunciation Assessment, and persistence.

## Runtime Settings

The service reads the following environment variables:

| Name | Default | Purpose |
| --- | --- | --- |
| `PYTHON_SERVICE_NAME` | `speech-evaluation` | Service name returned by `/health` |
| `PYTHON_SERVICE_HOST` | `127.0.0.1` | Local development host setting |
| `PYTHON_SERVICE_PORT` | `8100` | Temporary development port |
| `SPEECH_SERVICE_INTERNAL_TOKEN` | unset | Required token checked against `X-Internal-Token` for `/evaluate` |

Port `8100` is a temporary default. It is not a final fixed port before OI-002 is resolved. Change `PYTHON_SERVICE_PORT` when checking another port.

`SPEECH_SERVICE_INTERNAL_TOKEN` has no production default. Set it in the runtime environment. OI-007 still owns the final rotation policy and frequency.

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

Dockerfile and compose files are intentionally not created in T007-01, T007-02, or T007-03. Docker and VPS environment setup should be handled by later tasks.

## Evaluate Endpoint

T007-02 adds `POST /evaluate` as an internal API endpoint. It accepts `multipart/form-data` and checks the `X-Internal-Token` header against `SPEECH_SERVICE_INTERNAL_TOKEN`. T007-03 converts the uploaded audio file to temporary WAV bytes after token verification.

Required form fields:

- `submission_id`
- `question_id`
- `expected_duration`
- `feature_flags`
- `audio_file`

The `audio_file` field must be convertible by `ffmpeg`. DB connection, Azure connection, STT, Pronunciation Assessment, and evaluation result persistence are intentionally not implemented yet.

## Local Token Check With Docker

Run this from PowerShell with Docker Desktop running:

```powershell
cd C:\Projects\nihongo
docker run --rm -it -p 8100:8100 -e SPEECH_SERVICE_INTERNAL_TOKEN=test-internal-token -v ${PWD}\python:/app -w /app python:3.12-slim sh -c "pip install -r requirements.txt && uvicorn app.main:app --host 0.0.0.0 --port 8100"
```

This command does not install ffmpeg. It is useful for checking token rejection and request validation only. Use the ffmpeg check below for a valid audio conversion request.

Check an invalid token:

```powershell
curl.exe -X POST http://localhost:8100/evaluate `
  -H "X-Internal-Token: wrong-token" `
  -F "submission_id=00000000-0000-0000-0000-000000000001" `
  -F "question_id=1" `
  -F "expected_duration=60" `
  -F "feature_flags={""pronunciation"":true,""fluency"":true}" `
  -F "audio_file=@python\README.md;type=audio/webm"
```

Expected status: `401`.

Check a missing required parameter:

```powershell
curl.exe -X POST http://localhost:8100/evaluate `
  -H "X-Internal-Token: test-internal-token" `
  -F "question_id=1" `
  -F "expected_duration=60" `
  -F "feature_flags={""pronunciation"":true,""fluency"":true}" `
  -F "audio_file=@python\README.md;type=audio/webm"
```

Expected status: `422`.

## Local Evaluate Check With Docker And Ffmpeg

Run this from PowerShell with Docker Desktop running:

```powershell
cd C:\Projects\nihongo
docker run --rm -it -p 8100:8100 `
  -e SPEECH_SERVICE_INTERNAL_TOKEN=test-internal-token `
  -v ${PWD}\python:/app `
  -w /app `
  python:3.12-slim `
  sh -c "apt-get update && apt-get install -y ffmpeg && pip install -r requirements.txt && uvicorn app.main:app --host 0.0.0.0 --port 8100"
```

In another PowerShell window, generate a local sample file:

```powershell
cd C:\Projects\nihongo
docker run --rm -v ${PWD}:/work -w /work jrottenberg/ffmpeg:6.1-alpine `
  -y -f lavfi -i sine=frequency=440:duration=1 -c:a libopus sample.webm
```

Post the sample to `/evaluate`:

```powershell
curl.exe -i --max-time 30 -X POST http://localhost:8100/evaluate `
  -H "X-Internal-Token: test-internal-token" `
  -F "submission_id=00000000-0000-0000-0000-000000000001" `
  -F "question_id=1" `
  -F "expected_duration=60" `
  -F "feature_flags={""pronunciation"":true,""fluency"":true}" `
  -F "audio_file=@.\sample.webm;type=audio/webm"
```

Expected status: `200 OK`.

Expected response:

```json
{
  "status": "accepted",
  "submission_id": "00000000-0000-0000-0000-000000000001"
}
```

The generated `sample.webm` is local verification data only. Do not commit it. T007-03 does not persist generated WAV files.
