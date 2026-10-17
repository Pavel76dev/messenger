from __future__ import annotations

import os
from dataclasses import dataclass, field
from pathlib import Path

from dotenv import load_dotenv

ROOT = Path(__file__).resolve().parents[1]
load_dotenv(ROOT / ".env")


def _env(name: str, default: str = "") -> str:
    value = os.getenv(name)
    return default if value is None or value.strip() == "" else value.strip()


def resolve_whisper_model(raw: str) -> str:
    """
    Имя HF (small/medium) или путь к локальной папке модели.
    По умолчанию — services/asr/models/faster-whisper-small (без сети).
    """
    value = (raw or "").strip()
    bundled = ROOT / "models" / "faster-whisper-small"
    if not value:
        if bundled.is_dir() and (bundled / "model.bin").is_file():
            return str(bundled)
        return "small"

    path = Path(value)
    if not path.is_absolute():
        path = (ROOT / path).resolve()
    if path.is_dir() and (path / "model.bin").is_file():
        return str(path)

    # короткое имя HF: small / medium / …
    if bundled.is_dir() and (bundled / "model.bin").is_file() and value in {
        "tiny",
        "base",
        "small",
        "medium",
        "large-v2",
        "large-v3",
    }:
        # предпочитаем локальную копию вместо сети
        if value == "small":
            return str(bundled)

    return value


@dataclass
class Settings:
    api_host: str = field(default_factory=lambda: _env("API_HOST", "127.0.0.1"))
    api_port: int = field(default_factory=lambda: int(_env("API_PORT", "8060")))
    api_token: str = field(default_factory=lambda: _env("ASR_API_TOKEN", ""))
    whisper_model: str = field(
        default_factory=lambda: resolve_whisper_model(_env("WHISPER_MODEL", "small"))
    )
    whisper_device: str = field(default_factory=lambda: _env("WHISPER_DEVICE", "cpu"))
    whisper_compute_type: str = field(
        default_factory=lambda: _env("WHISPER_COMPUTE_TYPE", "int8")
    )
    whisper_language: str = field(default_factory=lambda: _env("WHISPER_LANGUAGE", "ru"))
    ffmpeg_path: str = field(default_factory=lambda: _env("FFMPEG_PATH", ""))
    work_dir: Path = field(default_factory=lambda: ROOT / "work")


settings = Settings()
settings.work_dir.mkdir(parents=True, exist_ok=True)
