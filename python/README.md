# Python Speech Evaluation Service

This directory contains the Python speech evaluation service.

At T007-04, the FastAPI foundation includes `GET /health` and the internal `POST /evaluate` endpoint. The health endpoint is limited to process liveness for the FastAPI service.

`POST /evaluate` converts uploaded WebM/Opus audio to temporary WAV bytes, sends those bytes to Azure Speech-to-Text, and returns the transcript with recognition metadata. The generated WAV is not persisted.

Not implemented after T007-04:

- DB connection
- Pronunciation Assessment
- Fluency Assessment
- Evaluation result persistence
- Laravel job-to-Python HTTP client

## Audio Conversion

T007-03 adds WebM/Opus to WAV conversion for the uploaded `audio_file`. The service calls the system `ffmpeg` binary with `subprocess.run()` and does not use Python wrapper packages such as pydub, moviepy, or av.

The conversion output format is:

- PCM WAV
- 16 kHz
- 16-bit signed samples
- mono

Uploaded input and generated WAV output are stored only in temporary files during conversion. They are deleted after success or failure. The generated WAV bytes are passed to Azure STT in T007-04. Later tasks own Pronunciation Assessment, Fluency Assessment, and persistence.

## Azure Speech-To-Text

T007-04 adds Azure Speech-to-Text using the Azure Cognitive Services Speech SDK for Python. The service uses continuous recognition as the main path and sets the speech recognition language to `ja-JP`.

`AZURE_SPEECH_ENDPOINT` is optional. When it is set, the service initializes the SDK with endpoint + key. Otherwise, it initializes the SDK with key + region. The default region is `japaneast`.

Duration values are intentionally separated:

- `audio_duration_seconds`: physical audio file duration; not derived in T007-04, so it is returned as `null`
- `recognized_duration_seconds`: duration reported by Azure for recognized speech segments when available
- `expected_duration`: request form field from the question; not used as a speed threshold in T007-04

`speech_rate.characters_per_minute` is calculated only when `recognized_duration_seconds` is positive. The `slow` / `appropriate` / `fast` thresholds are resolved as OI-015 MVP initial values:

- `slow`: `characters_per_minute < 180`
- `appropriate`: `180 <= characters_per_minute <= 320`
- `fast`: `characters_per_minute > 320`

These thresholds are initial values for T010-02 `config/comment_templates.php`, are not fixed in the DB schema, and may be adjusted after real Azure / speech evaluation data review. Python does not implement or persist the template comment decision in this step.

Continuous recognition stability for 40 / 60 / 90 / 120 second audio remains an OI-010 follow-up. T007-04 does not mark OI-010 as resolved. `raw_azure_response` is returned as minimal diagnostic JSON only; the 500KB retention policy remains OI-107.

## Runtime Settings

The service reads the following environment variables:

| Name | Default | Purpose |
| --- | --- | --- |
| `PYTHON_SERVICE_NAME` | `speech-evaluation` | Service name returned by `/health` |
| `PYTHON_SERVICE_HOST` | `127.0.0.1` | Local development host setting |
| `PYTHON_SERVICE_PORT` | `8100` | Temporary development port |
| `SPEECH_SERVICE_INTERNAL_TOKEN` | unset | Required token checked against `X-Internal-Token` for `/evaluate` |
| `AZURE_SPEECH_KEY` | unset | Azure Speech key required for STT |
| `AZURE_SPEECH_REGION` | `japaneast` | Azure Speech region used when endpoint is unset |
| `AZURE_SPEECH_ENDPOINT` | unset | Optional Azure Speech endpoint; takes precedence over region when set |

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

Dockerfile and compose files are intentionally not created in T007-01, T007-02, T007-03, or T007-04. Docker and VPS environment setup should be handled by later tasks.

## Evaluate Endpoint

T007-02 adds `POST /evaluate` as an internal API endpoint. It accepts `multipart/form-data` and checks the `X-Internal-Token` header against `SPEECH_SERVICE_INTERNAL_TOKEN`. T007-03 converts the uploaded audio file to temporary WAV bytes after token verification. T007-04 sends those WAV bytes to Azure STT and returns transcript metadata.

Required form fields:

- `submission_id`
- `question_id`
- `expected_duration`
- `feature_flags`
- `audio_file`

The `audio_file` field must be convertible by `ffmpeg` and recognizable by Azure STT. DB connection, Pronunciation Assessment, Fluency Assessment, and evaluation result persistence are intentionally not implemented yet.

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

## Local Evaluate Check With Docker, Ffmpeg, And Azure STT

Run this from PowerShell with Docker Desktop running:

```powershell
cd C:\Projects\nihongo
docker run --rm -it -p 8100:8100 `
  -e SPEECH_SERVICE_INTERNAL_TOKEN=test-internal-token `
  -e AZURE_SPEECH_KEY=$env:AZURE_SPEECH_KEY `
  -e AZURE_SPEECH_REGION=japaneast `
  -v ${PWD}\python:/app `
  -w /app `
  python:3.12-slim `
  sh -c "apt-get update && apt-get install -y ffmpeg && pip install -r requirements.txt && uvicorn app.main:app --host 0.0.0.0 --port 8100"
```

In another PowerShell window, prepare a local WebM/Opus file that contains short Japanese speech and name it `sample.webm`. Do not commit this file.

```powershell
cd C:\Projects\nihongo
# Place a local Japanese speech sample at .\sample.webm.
# Tone, silence, and noise-only files may correctly return 422.
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

Expected response shape:

```json
{
  "status": "success",
  "submission_id": "00000000-0000-0000-0000-000000000001",
  "transcript": "...",
  "audio_duration_seconds": null,
  "recognized_duration_seconds": 1.0,
  "speech_rate": {
    "characters_per_minute": 300.0
  },
  "azure_request_id": null,
  "azure_session_id": null,
  "raw_azure_response": {}
}
```

Before running the container, set `AZURE_SPEECH_KEY` in the local PowerShell environment. Do not paste the key into repository files, command history intended for sharing, screenshots, tests, or logs. `AZURE_SPEECH_ENDPOINT` may be passed with another `-e` option when endpoint-based initialization needs to be checked; it is optional and should not be committed.

The local `sample.webm` is verification data only. Do not commit it. T007-04 does not persist generated WAV files.

## Azure STT Error Classes

`/evaluate` maps the current minimum STT errors as follows:

- `422`: Azure NoMatch, empty transcript, silence, noise, or otherwise unrecognized speech
- `500`: missing Azure Speech configuration or SDK initialization configuration failure
- `503`: Azure service unavailable, timeout, network, or SDK runtime failure

## `/evaluate` Response Shape

Successful responses keep the existing `status=success` body with transcript, duration, speech rate, Azure request/session metadata, and `raw_azure_response`.

Error responses use `status=error` with `error_type`, `detail`, `retryable`, and `user_action`:

- `422`: `retryable=false`, `user_action=rerecord`
- `500`: `retryable=false`, `user_action=contact_admin`
- `503`: `retryable=true`, `user_action=retry_later`

Azure diagnostics are included in `diagnostic` when available. A `422` response is not a Laravel Job auto-retry target; it is for rerecording or noise/silence guidance, and it does not return empty evaluation data.

## Azure STT Smoke Test Diagnostics

When the local Azure smoke test returns `503`, inspect `diagnostic.category` in the `/evaluate` response body.

- `azure_canceled`: check `cancellation_reason`, `cancellation_error_code`, and sanitized `error_details`
- `AuthenticationFailure`: confirm the key, region, and Speech resource belong together
- `ConnectionFailure` or `ServiceTimeout`: check network access from the container, Azure temporary availability, and Linux/container runtime dependencies
- `sdk_exception`: check `exception_type` and sanitized `message`
- `azure_timeout`: continuous recognition did not complete before the service timeout

Azure Speech SDK may return a canceled result without `cancellation_error_code` or `error_details`. In that case, the diagnostic still includes `null` values, `*_available=false`, and `cancellation_details_source` so you can distinguish missing SDK values from extraction failure.

Cancellation details extraction depends on the installed Azure Speech SDK version. Use `cancellation_details_source` to confirm whether SDK details were read directly, a result fallback was used, or extraction failed.

`Canceled` with `cancellation_reason=EndOfStream` is not always an Azure outage. If no transcript was recognized and no error details were returned, the service treats it as unrecognized speech and returns `422` with diagnostic details.

When the response is `422` with `diagnostic.category` set to `no_match`, check that `sample.webm` contains audible Japanese speech, has enough duration, is not silence/noise, and was recorded at a usable microphone level.

Diagnostics intentionally report configuration booleans such as `key_configured`, `region_configured`, `endpoint_configured`, and `config_mode`. They do not include the Azure key, endpoint value, authorization headers, or environment variable values.

Do not commit `sample.webm`, real Azure secrets, or generated WAV files.

## FastAPI Startup Check

Before running the `/evaluate` smoke test, confirm FastAPI starts successfully. If startup fails with `Invalid args for response field`, check the `/evaluate` route decorator and return annotation. Diagnostic responses should not use a `dict | JSONResponse` union return annotation; use `response_model=None` when FastAPI response model generation must be disabled.
