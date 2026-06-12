"""Input route for speech evaluation requests."""

from hmac import compare_digest
from typing import Annotated, Any

from fastapi import APIRouter, File, Form, Header, HTTPException, UploadFile, status
from fastapi.responses import JSONResponse

from app.services.audio_conversion import (
    FfmpegNotFoundError,
    InvalidAudioFileError,
    convert_upload_to_wav,
)
from app.services.azure_stt import (
    AzureSttCanceledError,
    AzureSttConfigError,
    AzureSttNoMatchError,
    AzureSttServiceUnavailableError,
    AzureSttTimeoutError,
    build_speech_rate,
    transcribe_wav_bytes,
)
from app.settings import load_settings


router = APIRouter()


def _verify_internal_token(token: str | None) -> None:
    settings = load_settings()

    if not settings.internal_token or token is None:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Invalid internal token",
        )

    if not compare_digest(token, settings.internal_token):
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Invalid internal token",
        )


@router.post("/evaluate", response_model=None)
def evaluate(
    submission_id: Annotated[str, Form()],
    question_id: Annotated[int, Form()],
    expected_duration: Annotated[int, Form()],
    feature_flags: Annotated[str, Form()],
    audio_file: Annotated[UploadFile, File()],
    internal_token: Annotated[str | None, Header(alias="X-Internal-Token")] = None,
) -> Any:
    _verify_internal_token(internal_token)

    try:
        wav_bytes = convert_upload_to_wav(audio_file)
    except FfmpegNotFoundError:
        return _error_response(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            error_type="audio_conversion_dependency_missing",
            detail="Audio conversion dependency is not available",
            retryable=False,
            user_action="contact_admin",
        )
    except InvalidAudioFileError:
        return _error_response(
            status_code=status.HTTP_422_UNPROCESSABLE_ENTITY,
            error_type="audio_conversion_failed",
            detail="Audio conversion failed",
            retryable=False,
            user_action="rerecord",
        )

    try:
        stt_result = transcribe_wav_bytes(wav_bytes)
    except AzureSttNoMatchError as exc:
        return _error_response(
            status_code=status.HTTP_422_UNPROCESSABLE_ENTITY,
            error_type="speech_unrecognized",
            detail="Speech could not be recognized",
            retryable=False,
            user_action="rerecord",
            diagnostic=exc.diagnostic,
        )
    except AzureSttCanceledError as exc:
        return _error_response(
            status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
            error_type="azure_canceled",
            detail="Azure Speech recognition was canceled",
            retryable=True,
            user_action="retry_later",
            diagnostic=exc.diagnostic,
        )
    except AzureSttConfigError as exc:
        return _error_response(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            error_type="azure_configuration_unavailable",
            detail="Azure Speech configuration is not available",
            retryable=False,
            user_action="contact_admin",
            diagnostic=exc.diagnostic,
        )
    except (AzureSttTimeoutError, AzureSttServiceUnavailableError) as exc:
        return _error_response(
            status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
            error_type="azure_service_unavailable",
            detail="Azure Speech SDK error",
            retryable=True,
            user_action="retry_later",
            diagnostic=exc.diagnostic,
        )

    # Later tasks handle feature flags and persistence.
    _ = (question_id, expected_duration, feature_flags)

    return {
        "status": "success",
        "submission_id": submission_id,
        "transcript": stt_result.transcript,
        "audio_duration_seconds": stt_result.audio_duration_seconds,
        "recognized_duration_seconds": stt_result.recognized_duration_seconds,
        "speech_rate": build_speech_rate(
            stt_result.transcript,
            stt_result.recognized_duration_seconds,
        ),
        "azure_request_id": stt_result.azure_request_id,
        "azure_session_id": stt_result.azure_session_id,
        "raw_azure_response": stt_result.raw_azure_response,
    }


def _error_response(
    *,
    status_code: int,
    error_type: str,
    detail: str,
    retryable: bool,
    user_action: str,
    diagnostic: dict[str, object] | None = None,
) -> JSONResponse:
    content: dict[str, object] = {
        "status": "error",
        "error_type": error_type,
        "detail": detail,
        "retryable": retryable,
        "user_action": user_action,
    }

    if diagnostic is not None:
        content["diagnostic"] = diagnostic

    return JSONResponse(
        status_code=status_code,
        content=content,
    )
