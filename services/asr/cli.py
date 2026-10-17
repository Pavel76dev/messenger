"""
Локальная транскрибация для Laravel (без HTTP-сервиса).

  services/asr/.venv/.../python cli.py /path/to/audio.webm

Stdout: JSON { "text", "language", "duration_sec" }
Stderr: сообщения об ошибках
Exit 0 — успех, иначе ошибка.
"""

from __future__ import annotations

import json
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent
if str(ROOT) not in sys.path:
    sys.path.insert(0, str(ROOT))


def main(argv: list[str]) -> int:
    if len(argv) < 2:
        print("Usage: cli.py <audio-file>", file=sys.stderr)
        return 2

    src = Path(argv[1]).resolve()
    if not src.is_file():
        print(f"Файл не найден: {src}", file=sys.stderr)
        return 1

    try:
        from app.transcribe import transcribe_file

        result = transcribe_file(src)
    except Exception as exc:  # noqa: BLE001
        _write_stderr(str(exc))
        return 1

    # UTF-8 байты в stdout — иначе на Windows (cp1251) Laravel json_decode падает.
    _write_stdout(json.dumps(result, ensure_ascii=False))
    return 0


def _write_stdout(text: str) -> None:
    sys.stdout.buffer.write(text.encode("utf-8"))
    sys.stdout.buffer.write(b"\n")
    sys.stdout.buffer.flush()


def _write_stderr(text: str) -> None:
    sys.stderr.buffer.write(text.encode("utf-8", errors="replace"))
    sys.stderr.buffer.write(b"\n")
    sys.stderr.buffer.flush()


if __name__ == "__main__":
    raise SystemExit(main(sys.argv))
