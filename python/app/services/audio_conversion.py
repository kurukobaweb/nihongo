"""Audio conversion utilities for uploaded evaluation audio."""

from pathlib import Path
import shutil
import subprocess
import tempfile

from fastapi import UploadFile


class AudioConversionError(Exception):
    """Base error for audio conversion failures."""


class FfmpegNotFoundError(AudioConversionError):
    """Raised when the ffmpeg binary cannot be executed."""


class InvalidAudioFileError(AudioConversionError):
    """Raised when an uploaded file cannot be converted to WAV."""


def convert_upload_to_wav(upload_file: UploadFile) -> bytes:
    """Convert an uploaded WebM/Opus file to 16 kHz 16-bit mono WAV bytes."""
    with tempfile.TemporaryDirectory(prefix="speech-eval-") as temp_dir:
        temp_path = Path(temp_dir)
        input_path = temp_path / "input.webm"
        output_path = temp_path / "output.wav"

        _save_upload_file(upload_file, input_path)
        _run_ffmpeg(input_path, output_path)

        try:
            return output_path.read_bytes()
        except OSError as exc:
            raise InvalidAudioFileError("Converted WAV output is not available") from exc


def _save_upload_file(upload_file: UploadFile, input_path: Path) -> None:
    try:
        upload_file.file.seek(0)
        with input_path.open("wb") as input_file:
            shutil.copyfileobj(upload_file.file, input_file)
    except OSError as exc:
        raise InvalidAudioFileError("Audio upload could not be stored temporarily") from exc


def _run_ffmpeg(input_path: Path, output_path: Path) -> None:
    command = [
        "ffmpeg",
        "-hide_banner",
        "-loglevel",
        "error",
        "-y",
        "-i",
        str(input_path),
        "-ac",
        "1",
        "-ar",
        "16000",
        "-sample_fmt",
        "s16",
        str(output_path),
    ]

    try:
        result = subprocess.run(
            command,
            check=False,
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
        )
    except FileNotFoundError as exc:
        raise FfmpegNotFoundError("ffmpeg binary is not available") from exc

    if result.returncode != 0:
        raise InvalidAudioFileError("Audio conversion failed")
