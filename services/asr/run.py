"""Точка входа: python run.py (переключается на .venv)."""

from __future__ import annotations

import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent
if sys.platform == "win32":
    VENV_PYTHON = ROOT / ".venv" / "Scripts" / "python.exe"
else:
    VENV_PYTHON = ROOT / ".venv" / "bin" / "python"


def _ensure_venv() -> None:
    if not VENV_PYTHON.is_file():
        print(
            f"Нет виртуального окружения: {VENV_PYTHON}\n"
            "Создайте:\n"
            "  python -m venv .venv\n"
            "  .venv\\Scripts\\python.exe -m pip install -r requirements.txt",
            file=sys.stderr,
        )
        raise SystemExit(1)

    try:
        current = Path(sys.executable).resolve()
        wanted = VENV_PYTHON.resolve()
    except OSError:
        return

    if current == wanted:
        return

    raise SystemExit(
        subprocess.call([str(wanted), str(Path(__file__).resolve()), *sys.argv[1:]])
    )


_ensure_venv()

from dotenv import load_dotenv

load_dotenv(ROOT / ".env")

if __name__ == "__main__":
    import uvicorn

    from app.config import settings

    uvicorn.run(
        "app.main:app",
        host=settings.api_host,
        port=settings.api_port,
        reload=False,
    )
