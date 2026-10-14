# Architecture (OpenServer)

## Обзор

Один домен OpenServer (`http://messenger`): Laravel JSON API + React SPA.

```
browser → http://messenger → public/ (через корневой .htaccess)
  ├─ /api/*  → Laravel API (Sanctum Bearer)
  └─ /*      → Blade app.blade.php → Vite/React
```

## Каталоги

| Путь | Назначение |
|------|------------|
| `app/` | Laravel: модели, API-контроллеры |
| `resources/js/` | React + Redux Toolkit + MUI |
| `resources/views/app.blade.php` | HTML-оболочка SPA |
| `routes/api.php` | REST API |
| `routes/web.php` | SPA fallback |
| `public/` | Document root |
| `specs/` | Требования |

## База данных

MySQL OpenServer (как соседние домены):

- host `127.0.0.1:3306`
- database `messenger`
- user/password `root` / `root`

## Frontend

- Сборка: `npm run build` → `public/build`
- Dev: `npm run dev` (Vite) + OpenServer для PHP
- API base: same-origin (пустой `VITE_API_URL`)

## Файлы

- Диск Laravel `public` → `storage/app/public`, symlink `public/storage`
- Аватары: `avatars/{userId}.{ext}` → URL `/storage/...`
- Вложения сообщений: `attachments/{conversationId}/{uuid}_{name}` → URL `/storage/...`
- Аудио-клипы (личная библиотека): `audio_clips/{userId}/{uuid}.{ext}` → URL `/storage/...`
- Транскрипты клипов: поля `transcript_*` на `audio_clips`; ASR — локальный CLI `services/asr`
- В JSON API отдаём `avatar_url` / `attachments[].url` (публичные пути)

## Звонки (LiveKit)

- Сигналинг звонка: Laravel REST + Echo (ADR-002)
- Медиа: self-hosted LiveKit SFU (ADR-003), Docker: `docker/livekit/`
- Клиент получает JWT: `POST /api/calls/{id}/token`
- Env: `LIVEKIT_URL`, `LIVEKIT_API_KEY`, `LIVEKIT_API_SECRET`, `VITE_LIVEKIT_URL`

## ASR (внутри приложения)

- Код в репо: `services/asr` (faster-whisper). **Не** отдельный HTTP как api-llm.
- Laravel Job `TranscribeAudioClip` → subprocess `python cli.py <file>` → текст в БД
- Env: `ASR_PYTHON` (опц.), `ASR_TIMEOUT_SEC`
- LLM остаётся внешним (`LLM_API_URL` / api-llm)

## ИИ (api-llm)

- Бот — обычный `User` (`LLM_BOT_EMAIL`), диалог 1:1 (ADR-004)
- Laravel → `POST {LLM_API_URL}/api/chat` после сообщения пользователя (`GenerateAiReply` afterResponse)
- Env: `LLM_API_URL`, `LLM_API_TOKEN`, `LLM_BOT_*`, `LLM_TARGET` — см. `.env.example`
- **Production (gate):** на VPS `LLM_API_URL=http://127.0.0.1:18050`; домашний ПК держит SSH reverse tunnel на `api-llm:8050` (`gate-nr.local/scripts/start_llm_reverse_tunnel.py`, по аналогии с conveyor-bridge)

## Правила

1. Фича → SDLS (vibes/PDR/spec/tasks) → код.
2. Не смешивать бизнес-логику в React: только REST (+ Echo для realtime).
3. Realtime: WebSocket; polling — fallback (ADR-002).
4. Медиа звонков — только через LiveKit, не через Echo/PHP.
5. Минимальный дифф; спека — источник правды.
