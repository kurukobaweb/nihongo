"""Input route for speech evaluation requests."""

from hmac import compare_digest
from typing import Annotated

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


@router.post("/evaluate")
def evaluate(
    submission_id: Annotated[str, Form()],
    question_id: Annotated[int, Form()],
    expected_duration: Annotated[int, Form()],
    feature_flags: Annotated[str, Form()],
    audio_file: Annotated[UploadFile, File()],
    internal_token: Annotated[str | None, Header(alias="X-Internal-Token")] = None,
) -> dict[str, object] | JSONResponse:
    _verify_internal_token(internal_token)

    try:
        wav_bytes = convert_upload_to_wav(audio_file)
    except FfmpegNotFoundError as exc:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Audio conversion dependency is not available",
        ) from exc
    except InvalidAudioFileError as exc:
        raise HTTPException(
            status_code=status.HTTP_422_UNPROCESSABLE_ENTITY,
            detail="Audio conversion failed",
        ) from exc

    try:
        stt_result = transcribe_wav_bytes(wav_bytes)
    except AzureSttNoMatchError as exc:
        return _azure_error_response(
            status_code=status.HTTP_422_UNPROCESSABLE_ENTITY,
            detail="Speech could not be recognized",
            diagnostic=exc.diagnostic,
        )
    except AzureSttCanceledError as exc:
        return _azure_error_response(
            status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
            detail="Azure Speech recognition was canceled",
            diagnostic=exc.diagnostic,
        )
    except AzureSttConfigError as exc:
        return _azure_error_response(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Azure Speech configuration is not available",
            diagnostic=exc.diagnostic,
        )
    except (AzureSttTimeoutError, AzureSttServiceUnavailableError) as exc:
        return _azure_error_response(
            status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
            detail="Azure Speech SDK error",
            diagnostic=exc.diagnostic,
        )

    # Later tasks handle feature flags, final response shaping, and persistence.
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


def _azure_error_response(
    status_code: int,
    detail: str,
    diagnostic: dict[str, object],
) -> JSONResponse:
    return JSONResponse(
        status_code=status_code,
        content={
            "detail": detail,
            "diagnostic": diagnostic,
        },
    )
