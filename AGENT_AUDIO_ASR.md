# Инструкция агенту: аудио-клипы + локальная транскрибация

Читай этот файл, если задача про:

- запись / библиотеку аудио-фрагментов в messenger;
- отправку клипа в чат из библиотеки;
- транскрибацию клипов (faster-whisper);
- белый экран / зависший статус «транскрибация…»;
- модель Whisper, venv, ffmpeg, SSL к Hugging Face.

Код: `C:\OSPanel\domains\messenger`.  
Спеки: **013** (клипы), **014** (send + ASR), ADR-006.

LLM / api-llm — **отдельная** тема: `C:\OpenServer\nr\gate-nr.local\AGENT_AI_LLM.md`.

---

## 1. Главное правило продукта

| Компонент | Где живёт | Как вызывается |
|-----------|-----------|----------------|
| **LLM** (чат с ИИ) | **Внешний** сервис `C:\OpenServer\api-llm` | HTTP `LLM_API_URL` |
| **ASR** (транскрибация) | **Внутри** messenger `services/asr/` | Локальный subprocess CLI, **без** HTTP-демона |

Не превращай ASR обратно в `uvicorn`/`ASR_API_URL` «как LLM» — пользователь явно отказался.

`python run.py` в `services/asr` **не нужен** для работы UI (только ручная отладка HTTP).

---

## 2. Картина целиком

```
Браузер (library)
  → POST /api/audio-clips/{id}/transcribe   → сразу 202, status=pending
  → Laravel: AsrBackground → отдельный PHP-процесс
       php artisan audio-clip:transcribe {id}
         → AsrClient → services/asr/.venv/.../python cli.py <файл>
              → ffmpeg → wav
              → faster-whisper (локальная папка models/…)
              → JSON { text } → audio_clips.transcript_*
  → UI poll list → done → иконка «текст»
```

Отправка в чат:

```
POST /api/audio-clips/{id}/send { conversation_id }
  → copy файла в attachments/ → message + broadcast (как 006)
```

---

## 3. Спеки и ADR (не изобретать)

| Артефакт | Путь |
|----------|------|
| Клипы (запись, save/send после mic) | `specs/features/013-audio-clips.md` |
| Send из library + транскрипт | `specs/features/014-audio-transcription.md` |
| PDR 014 | `specs/pdr/014-audio-transcription.md` |
| ADR: ASR CLI внутри, LLM снаружи | `specs/adr/006-asr-service-in-repo.md` |
| Архитектура | `specs/architecture.md` (§ ASR) |

UI library: Play | Send | Transcribe | View(text) | Delete.

---

## 4. Ключевые файлы

### Backend

| Файл | Роль |
|------|------|
| `app/Models/AudioClip.php` | `transcript_status` idle/pending/done/error |
| `app/Http/Controllers/Api/AudioClipController.php` | CRUD, `send`, `transcribe`; stale pending → error |
| `app/Services/AsrBackground.php` | `start /B` (Windows) / background (Unix) artisan |
| `app/Services/AsrClient.php` | Process → `cli.py` |
| `app/Console/Commands/TranscribeAudioClipCommand.php` | `audio-clip:transcribe {id}` |
| `app/Jobs/TranscribeAudioClip.php` | устаревший путь afterResponse — **не использовать** для UI |
| `config/asr.php` | `ASR_PYTHON`, `ASR_TIMEOUT_SEC`, workdir |
| `database/migrations/2026_10_12_*_audio_clips*` | таблица |
| `database/migrations/2026_10_14_*_transcript*` | поля транскрипта |

### Frontend

| Файл | Роль |
|------|------|
| `resources/js/features/audioClips/*` | recorder, library, dialogs, API |
| `MessageComposer.jsx` | Mic + Library |
| `CallOverlay.jsx` | toggle-запись во время звонка |

### ASR runtime

| Путь | Роль |
|------|------|
| `services/asr/cli.py` | вход для Laravel |
| `services/asr/app/transcribe.py` | ffmpeg + Whisper |
| `services/asr/app/config.py` | `WHISPER_MODEL` → локальная папка |
| `services/asr/models/faster-whisper-small/` | локальные веса (`model.bin` ~480 МБ, **не в git**) |
| `services/asr/scripts/copy_whisper_from_cache.ps1` | копирование из HF-кэша / video |
| `services/asr/requirements.txt` | `faster-whisper`, `av>=11,<14`, `huggingface_hub<1` |
| `services/asr/run.py` | опциональный FastAPI — **UI не использует** |

API routes (`routes/api.php`, auth:sanctum):

- `GET/POST /api/audio-clips`
- `POST /api/audio-clips/{id}/transcribe`
- `POST /api/audio-clips/{id}/send`
- `DELETE /api/audio-clips/{id}`

---

## 5. Чеклист: локальный setup (один раз)

```powershell
cd C:\OSPanel\domains\messenger

# 1) storage symlink (иначе /storage/... не отдаётся → «no supported source»)
php artisan storage:link

# 2) миграции
php artisan migrate

# 3) ASR venv
cd services\asr
python -m venv .venv
.\.venv\Scripts\python.exe -m pip install -r requirements.txt

# 4) модель БЕЗ сети (из кэша HF, которым пользуется C:\OpenServer\video)
powershell -File .\scripts\copy_whisper_from_cache.ps1
# ожидаемо: models\faster-whisper-small\model.bin

# 5) .env в services/asr
# WHISPER_MODEL=models/faster-whisper-small
# WHISPER_LANGUAGE=ru
# FFMPEG_PATH=...  # обязательно: Apache не видит user PATH

# 6) ffmpeg: where в интерактивной оболочке ≠ PATH у httpd
where.exe ffmpeg
```

Фон из Apache наследует урезанный PATH → `ffmpeg не найден`, хотя `where ffmpeg` в PowerShell ок.  
Лечится `FFMPEG_PATH` в `services/asr/.env` (или автопоиск WinGet в `transcribe.py`).


В корневом `.env` messenger:

```
ASR_TIMEOUT_SEC=300
# ASR_PYTHON=   # опционально; иначе auto: services/asr/.venv/.../python
```

**Не** задавай `ASR_API_URL` — устарело.

Smoke CLI:

```powershell
cd C:\OSPanel\domains\messenger\services\asr
.\.venv\Scripts\python.exe cli.py C:\path\to\clip.webm
# stdout: {"text":"...","language":"ru","duration_sec":...}
```

---

## 6. Поведение записи (013)

- Вне звонка и во время ВКС: **toggle** (кнопка «западает»), не hold.
- Только **свой** микрофон (не remote mix звонка).
- После стопа: Сохранить (сервер `audio_clips`) / Отправить (вложение 006) / Отмена.
- Mic: secure context — лучше `http://messenger.localhost` или HTTPS (`mediaAccess.js`).

---

## 7. Типичные поломки

### Белый экран + вечный «транскрибация…»

Раньше Job шёл в том же PHP после ответа → OpenServer убивал процесс, статус оставался `pending`.

**Сейчас:** `AsrBackground` + `artisan audio-clip:transcribe`.  
Если снова `pending` > 3 мин → при `GET /api/audio-clips` станет `error` («повторите»).

Сброс вручную:

```sql
UPDATE audio_clips SET transcript_status='error',
  transcript_error='Сброшено вручную' WHERE transcript_status='pending';
```

### `no supported source` / не играет webm

Нет `public/storage` → `php artisan storage:link`.

### `open() got unexpected keyword argument 'metadata_errors'`

Слишком новый PyAV. В venv: `pip install "av>=11,<14"` (закреплено в `requirements.txt`).

### SSL / не качается модель с Hugging Face

Не качать в рантайме. Копировать:

```powershell
powershell -File services\asr\scripts\copy_whisper_from_cache.ps1
```

Источник: `%USERPROFILE%\.cache\huggingface\hub\models--Systran--faster-whisper-small\snapshots\…`  
(тот же кэш, что у `C:\OpenServer\video`; в папке video моделей нет).

Для качества как у video можно скопировать **medium** (~1.5 ГБ) и в `.env`:  
`WHISPER_MODEL=<путь к snapshot medium>` — по умолчанию в продукте **small**.

### Порт 8060 занят / Errno 10048

Кто-то запустил `python run.py`. Для UI не нужно. Убить процесс на 8060 или игнорировать.

### WebSocket `…/app/app/messenger-key` 404

В `resources/js/shared/realtime/echo.js`: `wsPath` не должен быть `/app` при пустом `APP_BASE` (pusher-js сам добавляет `/app/{key}`).

### Транскрибация «ошибка», текст пустой

Смотри `transcript_error` в API/БД и вывод:

```powershell
php artisan audio-clip:transcribe {id}
```

Частые причины: нет venv, нет ffmpeg, нет `models/.../model.bin`, битый webm.

### «Транскрибация прервана или зависла. Нажмите ещё раз.» / вечный pending, пустой laravel.log

`pending` > 3 мин: фоновый процесс **не стартовал** или умер до bootstrap (в `laravel.log` пусто).

Типично на OpenServer:

1. **Веб-SAPI = `apache2handler`, `PHP_BINARY=httpd.exe`** (OpenServer) → рядом с Apache нет `php.exe`.  
   `AsrBackground` ищет `modules/php/PHP_X.Y/php.exe` или `ASR_PHP` в `.env`, пишет `.cmd` и делает `start /B`.
2. **`php-cgi.exe`** → им artisan тоже нельзя; берём соседний/OSPanel `php.exe`.
3. **CLI JSON в cp1251** → `cli.py` пишет UTF-8.
4. Смотри `storage/logs/asr-background.log` и `AsrBackground: windows spawn` в `laravel.log`.

Если 500 «Не найден php.exe» — в корневой `.env`:

```
ASR_PHP=C:\OSPanel\modules\php\PHP_8.1\php.exe
```

и `php artisan config:clear`.

Проверка:

```powershell
php artisan audio-clip:transcribe {id}
# ожидаемо: OK clip {id}

Get-Content storage\logs\asr-background.log -Tail 40
```

Сброс зависших:

```sql
UPDATE audio_clips SET transcript_status='error',
  transcript_error='Сброшено вручную' WHERE transcript_status='pending';
```

---

## 8. Деплой / прод (заметки)

На VPS рядом с Laravel нужны:

1. `services/asr/.venv` + `requirements.txt`
2. Папка модели `models/faster-whisper-small` (выложить файлом, не качать с HF через прокси)
3. `ffmpeg` на сервере
4. PHP должен уметь `popen`/`start` (Windows) или background `exec` (Linux) для `AsrBackground`

Это **не** reverse-tunnel как у LLM: инференс на той же машине, где PHP (или отдельный app-server с общим storage — тогда усложнять только по явной задаче).

Gate-деплой messenger: см. `gate-nr.local` / `AGENT_DEPLOY.md`. Отдельного ASR-туннеля нет и не планировалось.

---

## 9. Что не делать

- Не возвращать обязательный HTTP ASR-сервис / `ASR_API_URL`.
- Не звать `C:\OpenServer\video:8020` из Laravel.
- Не слать аудио в облачный ASR.
- Не коммитить `models/**/model.bin` (~480 МБ) — в `.gitignore`.
- Не требовать `python run.py` в инструкциях пользователю.
- Не блокировать HTTP-запрос на весь Whisper (только фоновый artisan).

---

## 10. Быстрый чеклист перед правками

1. Прочитай `specs/features/014-*.md` + ADR-006.
2. Убедись, что UI → 202 → `audio-clip:transcribe` → `cli.py` → локальная модель.
3. После изменений ASR: smoke `cli.py` на реальном webm из `storage/app/public/audio_clips/`.
4. Минимальный дифф; язык UI/ошибок — русский.
