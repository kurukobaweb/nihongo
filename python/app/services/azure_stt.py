"""Azure Speech-to-Text service integration."""

from __future__ import annotations

from dataclasses import dataclass
import json
from pathlib import Path
import re
import tempfile
import threading
from typing import Any

from app.settings import Settings, load_settings

try:
    import azure.cognitiveservices.speech as speechsdk
except ImportError:  # pragma: no cover - exercised only when dependency is absent.
    speechsdk = None


SPEECH_LANGUAGE = "ja-JP"
DEFAULT_STT_TIMEOUT_SECONDS = 150
TICKS_PER_SECOND = 10_000_000
MAX_DIAGNOSTIC_MESSAGE_LENGTH = 1500


class AzureSttError(Exception):
    """Base error for Azure STT failures."""

    def __init__(
        self,
        message: str,
        diagnostic: dict[str, Any] | None = None,
    ) -> None:
        super().__init__(message)
        self.diagnostic = diagnostic or {}


class AzureSttConfigError(AzureSttError):
    """Raised when Azure Speech configuration is missing or invalid."""


class AzureSttNoMatchError(AzureSttError):
    """Raised when Azure does not return recognizable speech."""


class AzureSttServiceUnavailableError(AzureSttError):
    """Raised for Azure service, network, or SDK runtime failures."""


class AzureSttCanceledError(AzureSttServiceUnavailableError):
    """Raised when Azure cancels recognition and returns cancellation details."""


class AzureSttTimeoutError(AzureSttServiceUnavailableError):
    """Raised when continuous recognition does not complete in time."""


@dataclass(frozen=True)
class AzureSttResult:
    transcript: str
    audio_duration_seconds: float | None
    recognized_duration_seconds: float | None
    azure_request_id: str | None
    azure_session_id: str | None
    raw_azure_response: dict[str, Any]


def transcribe_wav_bytes(
    wav_bytes: bytes,
    settings: Settings | None = None,
    timeout_seconds: int = DEFAULT_STT_TIMEOUT_SECONDS,
) -> AzureSttResult:
    """Transcribe WAV bytes with Azure Speech continuous recognition."""
    active_settings = settings or load_settings()
    speech_config = _build_speech_config(active_settings)
    config_diagnostic = _config_diagnostic(active_settings)

    with tempfile.TemporaryDirectory(prefix="speech-stt-") as temp_dir:
        try:
            wav_path = Path(temp_dir) / "input.wav"
            wav_path.write_bytes(wav_bytes)
            audio_config = speechsdk.audio.AudioConfig(filename=str(wav_path))
            recognizer = speechsdk.SpeechRecognizer(
                speech_config=speech_config,
                audio_config=audio_config,
            )
        except Exception as exc:
            raise AzureSttServiceUnavailableError(
                "Azure Speech SDK error",
                diagnostic=_sdk_exception_diagnostic(exc, config_diagnostic),
            ) from exc

        return _run_continuous_recognition(
            recognizer,
            timeout_seconds,
            config_diagnostic,
        )


def build_speech_rate(
    transcript: str,
    recognized_duration_seconds: float | None,
) -> dict[str, float | None]:
    if recognized_duration_seconds is None or recognized_duration_seconds <= 0:
        return {"characters_per_minute": None}

    return {
        "characters_per_minute": len(transcript) / recognized_duration_seconds * 60,
    }


def _build_speech_config(settings: Settings):
    if speechsdk is None:
        raise AzureSttConfigError(
            "Azure Speech SDK is not installed",
            diagnostic={
                "category": "config_error",
                "message": "Azure Speech SDK is not installed",
                "config": _config_diagnostic(settings),
            },
        )

    if not settings.azure_speech_key:
        raise AzureSttConfigError(
            "AZURE_SPEECH_KEY is required",
            diagnostic={
                "category": "config_error",
                "message": "AZURE_SPEECH_KEY is required",
                "config": _config_diagnostic(settings),
            },
        )

    try:
        if settings.azure_speech_endpoint:
            speech_config = speechsdk.SpeechConfig(
                subscription=settings.azure_speech_key,
                endpoint=settings.azure_speech_endpoint,
            )
        else:
            if not settings.azure_speech_region:
                raise AzureSttConfigError(
                    "AZURE_SPEECH_REGION is required",
                    diagnostic={
                        "category": "config_error",
                        "message": "AZURE_SPEECH_REGION is required",
                        "config": _config_diagnostic(settings),
                    },
                )
            speech_config = speechsdk.SpeechConfig(
                subscription=settings.azure_speech_key,
                region=settings.azure_speech_region,
            )
    except AzureSttConfigError:
        raise
    except Exception as exc:
        raise AzureSttConfigError(
            "Azure Speech SDK configuration failed",
            diagnostic={
                "category": "config_error",
                "exception_type": type(exc).__name__,
                "message": sanitize_diagnostic_message(str(exc)),
                "config": _config_diagnostic(settings),
            },
        ) from exc

    speech_config.speech_recognition_language = SPEECH_LANGUAGE
    return speech_config


def _run_continuous_recognition(
    recognizer,
    timeout_seconds: int,
    config_diagnostic: dict[str, Any],
) -> AzureSttResult:
    done = threading.Event()
    segments: list[dict[str, Any]] = []
    no_match_segments: list[dict[str, Any]] = []
    cancel_details: list[dict[str, Any]] = []
    session_id: str | None = None

    def recognized(event) -> None:
        result = event.result
        reason = _reason_name(result.reason)
        segment = _segment_from_result(result, reason)

        if reason == "RecognizedSpeech" and segment["text"]:
            segments.append(segment)
            return

        if reason == "NoMatch":
            no_match_segments.append(segment)

    def canceled(event) -> None:
        cancel_details.append(_cancellation_diagnostic(event, config_diagnostic))
        done.set()

    def session_started(event) -> None:
        nonlocal session_id
        session_id = getattr(event, "session_id", None)

    def session_stopped(event) -> None:
        done.set()

    recognizer.recognized.connect(recognized)
    recognizer.canceled.connect(canceled)
    recognizer.session_started.connect(session_started)
    recognizer.session_stopped.connect(session_stopped)

    try:
        recognizer.start_continuous_recognition()
        if not done.wait(timeout_seconds):
            raise AzureSttTimeoutError(
                "Azure STT recognition timed out",
                diagnostic={
                    "category": "azure_timeout",
                    "message": "Continuous recognition timed out",
                    "config": config_diagnostic,
                },
            )
    except AzureSttError:
        raise
    except Exception as exc:
        raise AzureSttServiceUnavailableError(
            "Azure Speech SDK error",
            diagnostic=_sdk_exception_diagnostic(exc, config_diagnostic),
        ) from exc
    finally:
        try:
            recognizer.stop_continuous_recognition()
        except Exception:
            pass

    if cancel_details:
        diagnostic = cancel_details[0]
        if session_id:
            diagnostic["azure_session_id"] = session_id
        raise AzureSttCanceledError(
            "Azure Speech recognition was canceled",
            diagnostic=diagnostic,
        )

    transcript_parts = [segment["text"] for segment in segments if segment["text"]]
    transcript = " ".join(transcript_parts).strip()
    if not transcript:
        raw_no_match = {"segments": no_match_segments}
        raise AzureSttNoMatchError(
            json.dumps(raw_no_match, ensure_ascii=False),
            diagnostic={
                "category": "no_match",
                "result_reason": "NoMatch",
                "config": config_diagnostic,
                "segments": no_match_segments,
            },
        )

    durations = [
        segment["duration_seconds"]
        for segment in segments
        if segment["duration_seconds"] is not None
    ]
    recognized_duration_seconds = sum(durations) if durations else None

    return AzureSttResult(
        transcript=transcript,
        audio_duration_seconds=None,
        recognized_duration_seconds=recognized_duration_seconds,
        azure_request_id=_first_non_empty(
            segment.get("azure_request_id") for segment in segments
        ),
        azure_session_id=session_id,
        raw_azure_response={
            "recognition_mode": "continuous",
            "language": SPEECH_LANGUAGE,
            "segments": segments,
        },
    )


def _segment_from_result(result, reason: str) -> dict[str, Any]:
    raw_json = _result_json(result)
    return {
        "reason": reason,
        "text": (getattr(result, "text", "") or "").strip(),
        "offset_seconds": _ticks_to_seconds(getattr(result, "offset", None)),
        "duration_seconds": _ticks_to_seconds(getattr(result, "duration", None)),
        "azure_request_id": _raw_json_value(raw_json, "Id"),
        "raw_json": raw_json,
    }


def _result_json(result) -> dict[str, Any]:
    properties = getattr(result, "properties", None)
    property_id = getattr(
        getattr(speechsdk, "PropertyId", object()),
        "SpeechServiceResponse_JsonResult",
        None,
    )

    if properties is None or property_id is None:
        return {}

    try:
        raw_value = properties.get_property(property_id)
    except Exception:
        return {}

    if not raw_value:
        return {}

    try:
        parsed = json.loads(raw_value)
    except json.JSONDecodeError:
        return {"raw": raw_value}

    return parsed if isinstance(parsed, dict) else {"raw": parsed}


def _cancellation_diagnostic(
    event,
    config_diagnostic: dict[str, Any],
) -> dict[str, Any]:
    result = getattr(event, "result", None)
    details = _cancellation_details_from_result(result)
    error_details = getattr(details, "error_details", None)

    diagnostic = {
        "category": "azure_canceled",
        "result_reason": "Canceled",
        "cancellation_reason": _reason_name(getattr(details, "reason", None)),
        "cancellation_error_code": _reason_name(getattr(details, "error_code", None)),
        "error_details": sanitize_diagnostic_message(error_details),
        "azure_request_id": _request_id_from_result(result),
        "config": config_diagnostic,
    }

    return {key: value for key, value in diagnostic.items() if value is not None}


def _cancellation_details_from_result(result):
    cancellation_details = getattr(speechsdk, "CancellationDetails", None)
    if result is not None and cancellation_details is not None:
        try:
            return cancellation_details.from_result(result)
        except Exception:
            pass

    return result or object()


def _reason_name(reason) -> str | None:
    if reason is None:
        return None
    return getattr(reason, "name", str(reason))


def _ticks_to_seconds(value: int | None) -> float | None:
    if value is None or value <= 0:
        return None
    return value / TICKS_PER_SECOND


def _raw_json_value(raw_json: dict[str, Any], key: str) -> str | None:
    value = raw_json.get(key)
    return value if isinstance(value, str) and value else None


def _request_id_from_result(result) -> str | None:
    if result is None:
        return None
    return _raw_json_value(_result_json(result), "Id")


def _config_diagnostic(settings: Settings) -> dict[str, bool | str]:
    endpoint_configured = bool(settings.azure_speech_endpoint)
    return {
        "key_configured": bool(settings.azure_speech_key),
        "region_configured": bool(settings.azure_speech_region),
        "endpoint_configured": endpoint_configured,
        "config_mode": "endpoint" if endpoint_configured else "key_region",
    }


def _sdk_exception_diagnostic(
    exc: Exception,
    config_diagnostic: dict[str, Any],
) -> dict[str, Any]:
    return {
        "category": "sdk_exception",
        "exception_type": type(exc).__name__,
        "message": sanitize_diagnostic_message(str(exc)),
        "config": config_diagnostic,
    }


def sanitize_diagnostic_message(message: str | None) -> str | None:
    if message is None:
        return None

    cleaned = "".join(
        char if char.isprintable() and char not in "\r\n\t" else " "
        for char in str(message)
    )
    cleaned = re.sub(r"\s+", " ", cleaned).strip()
    cleaned = re.sub(r"https?://\S+", "[redacted_url]", cleaned)
    cleaned = re.sub(r"(?i)(subscription-key|api-key|key)=\S+", r"\1=[redacted]", cleaned)
    cleaned = re.sub(r"\b[A-Za-z0-9+/=_-]{24,}\b", "[redacted_token]", cleaned)

    if len(cleaned) > MAX_DIAGNOSTIC_MESSAGE_LENGTH:
        return cleaned[:MAX_DIAGNOSTIC_MESSAGE_LENGTH] + "...[truncated]"

    return cleaned


def _first_non_empty(values) -> str | None:
    for value in values:
        if value:
            return value
    return None
