"""Input route for speech evaluation requests."""

from hmac import compare_digest
from typing import Annotated

from fastapi import APIRouter, File, Form, Header, HTTPException, UploadFile, status

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
) -> dict[str, str]:
    _verify_internal_token(internal_token)

    # T007-02 only accepts the request. Later tasks handle audio processing and flags.
    _ = (question_id, expected_duration, feature_flags, audio_file)

    return {
        "status": "accepted",
        "submission_id": submission_id,
    }
