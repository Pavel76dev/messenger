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


@dataclass
class Settings:
    api_host: str = field(default_factory=lambda: _env("API_HOST", "127.0.0.1"))
    api_port: int = field(default_factory=lambda: int(_env("API_PORT", "8060")))
    api_token: str = field(default_factory=lambda: _env("ASR_API_TOKEN", ""))
    whisper_model: str = field(default_factory=lambda: _env("WHISPER_MODEL", "small"))
    whisper_device: str = field(default_factory=lambda: _env("WHISPER_DEVICE", "cpu"))
    whisper_compute_type: str = field(
        default_factory=lambda: _env("WHISPER_COMPUTE_TYPE", "int8")
    )
    whisper_language: str = field(default_factory=lambda: _env("WHISPER_LANGUAGE", "ru"))
    ffmpeg_path: str = field(default_factory=lambda: _env("FFMPEG_PATH", ""))
    work_dir: Path = field(default_factory=lambda: ROOT / "work")


settings = Settings()
settings.work_dir.mkdir(parents=True, exist_ok=True)
