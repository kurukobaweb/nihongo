import subprocess
from io import BytesIO
from pathlib import Path

import pytest
from fastapi import UploadFile

from app.services import audio_conversion
from app.services.audio_conversion import (
    FfmpegNotFoundError,
    InvalidAudioFileError,
    convert_upload_to_wav,
)


def _upload_file() -> UploadFile:
    return UploadFile(filename="sample.webm", file=BytesIO(b"webm audio bytes"))


def test_convert_upload_to_wav_runs_ffmpeg_and_returns_wav_bytes(monkeypatch) -> None:
    seen: dict[str, object] = {}

    def fake_run(command, check, stdout, stderr):
        input_path = Path(command[command.index("-i") + 1])
        output_path = Path(command[-1])
        seen["command"] = command
        seen["input_path"] = input_path
        seen["output_path"] = output_path
        seen["check"] = check
        seen["stdout"] = stdout
        seen["stderr"] = stderr

        assert input_path.read_bytes() == b"webm audio bytes"
        output_path.write_bytes(b"wav bytes")
        return subprocess.CompletedProcess(command, 0, b"", b"")

    monkeypatch.setattr(audio_conversion.subprocess, "run", fake_run)

    result = convert_upload_to_wav(_upload_file())

    assert result == b"wav bytes"
    assert seen["check"] is False
    assert seen["stdout"] is subprocess.PIPE
    assert seen["stderr"] is subprocess.PIPE
    command = seen["command"]
    assert "-y" in command
    assert "-i" in command
    assert command[command.index("-ac") + 1] == "1"
    assert command[command.index("-ar") + 1] == "16000"
    assert command[command.index("-sample_fmt") + 1] == "s16"
    assert not seen["input_path"].exists()
    assert not seen["output_path"].exists()


def test_convert_upload_to_wav_raises_invalid_audio_on_ffmpeg_failure(
    monkeypatch,
) -> None:
    seen: dict[str, Path] = {}

    def fake_run(command, check, stdout, stderr):
        seen["input_path"] = Path(command[command.index("-i") + 1])
        seen["output_path"] = Path(command[-1])
        return subprocess.CompletedProcess(command, 1, b"", b"invalid audio")

    monkeypatch.setattr(audio_conversion.subprocess, "run", fake_run)

    with pytest.raises(InvalidAudioFileError):
        convert_upload_to_wav(_upload_file())

    assert not seen["input_path"].exists()
    assert not seen["output_path"].exists()


def test_convert_upload_to_wav_raises_ffmpeg_not_found(monkeypatch) -> None:
    seen: dict[str, Path] = {}

    def fake_run(command, check, stdout, stderr):
        seen["input_path"] = Path(command[command.index("-i") + 1])
        seen["output_path"] = Path(command[-1])
        raise FileNotFoundError("ffmpeg")

    monkeypatch.setattr(audio_conversion.subprocess, "run", fake_run)

    with pytest.raises(FfmpegNotFoundError):
        convert_upload_to_wav(_upload_file())

    assert not seen["input_path"].exists()
    assert not seen["output_path"].exists()
