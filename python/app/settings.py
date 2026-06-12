"""Runtime settings for the speech evaluation service."""

from dataclasses import dataclass
import os


DEFAULT_SERVICE_NAME = "speech-evaluation"
DEFAULT_HOST = "127.0.0.1"
DEFAULT_PORT = 8100
DEFAULT_AZURE_SPEECH_REGION = "japaneast"


@dataclass(frozen=True)
class Settings:
    service_name: str
    host: str
    port: int
    internal_token: str | None
    azure_speech_key: str | None
    azure_speech_region: str | None
    azure_speech_endpoint: str | None


def _read_port(value: str | None) -> int:
    if value is None or value == "":
        return DEFAULT_PORT

    try:
        port = int(value)
    except ValueError as exc:
        raise ValueError("PYTHON_SERVICE_PORT must be an integer") from exc

    if port < 1 or port > 65535:
        raise ValueError("PYTHON_SERVICE_PORT must be between 1 and 65535")

    return port


def load_settings() -> Settings:
    return Settings(
        service_name=os.getenv("PYTHON_SERVICE_NAME", DEFAULT_SERVICE_NAME),
        host=os.getenv("PYTHON_SERVICE_HOST", DEFAULT_HOST),
        port=_read_port(os.getenv("PYTHON_SERVICE_PORT")),
        internal_token=os.getenv("SPEECH_SERVICE_INTERNAL_TOKEN"),
        azure_speech_key=os.getenv("AZURE_SPEECH_KEY"),
        azure_speech_region=os.getenv(
            "AZURE_SPEECH_REGION",
            DEFAULT_AZURE_SPEECH_REGION,
        ),
        azure_speech_endpoint=os.getenv("AZURE_SPEECH_ENDPOINT"),
    )
