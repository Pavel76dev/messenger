# ASR внутри messenger (`services/asr`)

Транскрибация **локально в приложении** (не внешний сервис вроде api-llm).

Laravel Job запускает CLI:

```text
.venv/…/python cli.py /path/to/clip.webm
→ JSON { "text", "language", "duration_sec" }
```

## Установка (один раз)

```bash
cd services/asr
python -m venv .venv
.venv\Scripts\python.exe -m pip install -r requirements.txt
copy .env.example .env
```

Нужен **ffmpeg** в PATH (или `FFMPEG_PATH` в `.env`).

## Проверка вручную

```bash
cd services/asr
.venv\Scripts\python.exe cli.py C:\path\to\audio.webm
```

Опциональный HTTP (`python run.py`) — только для отладки, мессенджер его **не использует**.
