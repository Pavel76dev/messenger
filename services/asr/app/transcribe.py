from __future__ import annotations

import os
import shutil
import subprocess
import uuid
from pathlib import Path

from app.config import settings

_whisper_model = None


def _ffmpeg_executable() -> str | None:
    if settings.ffmpeg_path:
        path = Path(settings.ffmpeg_path)
        if path.is_file():
            return str(path)

    which = shutil.which("ffmpeg")
    if which:
        return which

    # Apache/служба Windows часто без user PATH (winget ffmpeg туда не попадает).
    return _discover_ffmpeg()


def _discover_ffmpeg() -> str | None:
    candidates: list[Path] = []
    local = os.environ.get("LOCALAPPDATA") or os.environ.get("USERPROFILE")
    if local:
        winget = Path(local) / "Microsoft" / "WinGet" / "Packages"
        if winget.is_dir():
            candidates.extend(winget.glob("Gyan.FFmpeg*/ffmpeg-*/bin/ffmpeg.exe"))
            candidates.extend(winget.glob("Gyan.FFmpeg*/**/ffmpeg.exe"))
    for folder in (
        Path(r"C:\ffmpeg\bin"),
        Path(r"C:\OSPanel\modules\ffmpeg\bin"),
        Path(r"C:\Program Files\ffmpeg\bin"),
        Path(r"C:\ProgramData\chocolatey\bin"),
    ):
        candidates.append(folder / "ffmpeg.exe")

    for path in candidates:
        if path.is_file():
            return str(path)
    return None


def extract_wav(src: Path, dst: Path) -> None:
    ffmpeg = _ffmpeg_executable()
    if not ffmpeg:
        raise RuntimeError(
            "ffmpeg не найден. Установите ffmpeg в PATH или задайте FFMPEG_PATH."
        )
    cmd = [
        ffmpeg,
        "-y",
        "-i",
        str(src),
        "-vn",
        "-ar",
        "16000",
        "-ac",
        "1",
        "-c:a",
        "pcm_s16le",
        str(dst),
    ]
    result = subprocess.run(cmd, capture_output=True, text=True)
    if result.returncode != 0:
        stderr = (result.stderr or "").strip()
        raise RuntimeError(f"ffmpeg ошибка: {stderr[-500:]}")


def _get_whisper_model():
    global _whisper_model
    if _whisper_model is None:
        from faster_whisper import WhisperModel

        model_id = settings.whisper_model
        is_local_dir = Path(model_id).is_dir()
        # Локальная папка / кэш — без сети. Имя HF — только если локальной копии нет.
        _whisper_model = WhisperModel(
            model_id,
            device=settings.whisper_device,
            compute_type=settings.whisper_compute_type,
            local_files_only=is_local_dir,
        )
    return _whisper_model


def transcribe_file(src: Path) -> dict:
    """Возвращает { text, language, duration_sec }."""
    work = settings.work_dir / uuid.uuid4().hex
    work.mkdir(parents=True, exist_ok=True)
    wav_path = work / "audio.wav"
    try:
        extract_wav(src, wav_path)
        model = _get_whisper_model()
        segments_iter, info = model.transcribe(
            str(wav_path),
            language=settings.whisper_language or None,
            vad_filter=True,
            beam_size=5,
        )
        parts: list[str] = []
        for segment in segments_iter:
            text = (segment.text or "").strip()
            if text:
                parts.append(text)
        text = "\n".join(parts).strip()
        return {
            "text": text,
            "language": getattr(info, "language", settings.whisper_language),
            "duration_sec": float(getattr(info, "duration", 0) or 0),
        }
    finally:
        shutil.rmtree(work, ignore_errors=True)
