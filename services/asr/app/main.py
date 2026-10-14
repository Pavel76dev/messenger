from __future__ import annotations

import shutil
import tempfile
import uuid
from pathlib import Path

from fastapi import Depends, FastAPI, File, Header, HTTPException, UploadFile
from fastapi.responses import JSONResponse

from app.config import settings
from app.transcribe import _ffmpeg_executable, transcribe_file

ALLOWED_SUFFIXES = {
    ".webm",
    ".ogg",
    ".oga",
    ".wav",
    ".mp3",
    ".m4a",
    ".mp4",
    ".mpeg",
    ".mpga",
}


def _check_token(authorization: str | None = Header(default=None)) -> None:
    expected = settings.api_token
    if not expected:
        return
    if not authorization or not authorization.startswith("Bearer "):
        raise HTTPException(status_code=401, detail="Нужен Bearer-токен")
    token = authorization.removeprefix("Bearer ").strip()
    if token != expected:
        raise HTTPException(status_code=401, detail="Неверный токен")


app = FastAPI(
    title="Messenger ASR",
    description="Локальная транскрибация аудио-клипов (faster-whisper)",
)


@app.get("/api/health")
def health() -> dict:
    return {
        "ok": True,
        "whisper_model": settings.whisper_model,
        "whisper_device": settings.whisper_device,
        "whisper_compute_type": settings.whisper_compute_type,
        "whisper_language": settings.whisper_language,
        "ffmpeg": bool(_ffmpeg_executable()),
    }


@app.post("/api/transcribe")
async def transcribe(
    file: UploadFile = File(...),
    _: None = Depends(_check_token),
) -> JSONResponse:
    if not file.filename:
        raise HTTPException(status_code=400, detail="Имя файла пустое")

    suffix = Path(file.filename).suffix.lower() or ".webm"
    if suffix not in ALLOWED_SUFFIXES:
        raise HTTPException(status_code=400, detail=f"Формат не поддерживается: {suffix}")

    content = await file.read()
    if not content:
        raise HTTPException(status_code=400, detail="Пустой файл")
    if len(content) > 12 * 1024 * 1024:
        raise HTTPException(status_code=400, detail="Файл больше 12 МБ")

    tmp_dir = Path(tempfile.mkdtemp(prefix="asr_", dir=str(settings.work_dir)))
    src = tmp_dir / f"{uuid.uuid4().hex}{suffix}"
    try:
        src.write_bytes(content)
        result = transcribe_file(src)
    except RuntimeError as exc:
        raise HTTPException(status_code=500, detail=str(exc)) from exc
    except Exception as exc:  # noqa: BLE001
        raise HTTPException(
            status_code=500,
            detail=f"Ошибка транскрибации: {exc}",
        ) from exc
    finally:
        shutil.rmtree(tmp_dir, ignore_errors=True)

    return JSONResponse(
        {
            "text": result.get("text") or "",
            "language": result.get("language"),
            "duration_sec": result.get("duration_sec"),
        }
    )
