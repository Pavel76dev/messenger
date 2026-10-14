---
id: 014-audio-transcription
title: Библиотека — отправка + транскрибация (ASR в репо)
status: implemented
owner: team
created: 2026-10-14
---

# 014 — Отправка из библиотеки + транскрибация

## Контекст

PDR + ADR-006. ASR **внутри** messenger (`services/asr/cli.py`), не внешний HTTP как LLM.

## Цель

Send клипа в открытый чат; транскрибировать локально; открыть текст.

## User stories

- Из library отправляю клип в текущий чат.
- Жму транскрибацию, вижу pending, потом текст.
- Открываю готовый транскрипт иконкой просмотра.

## Требования

### Must

- [x] Library: Send (нужен `conversationId`)
- [x] `POST /api/audio-clips/{id}/send` `{ conversation_id }` → сообщение + attachment
- [x] Library: Transcribe + View (View только при `done`)
- [x] `POST /api/audio-clips/{id}/transcribe` → `pending`, Job → ASR
- [x] API клипа: `transcript_status` (`idle|pending|done|error`), `transcript_text`, `transcript_error`
- [x] `services/asr/cli.py` → JSON `{ text }`; Laravel вызывает локальный subprocess
- [x] `faster-whisper>=1.1.0`, язык `ru`; без `ASR_API_URL` / облака
- [x] Ошибки на русском; чужой клип → 403

### Should

- [x] Poll list пока есть pending
- [x] Повторный transcribe перезаписывает

### Must not

- [ ] Не звать `C:\OpenServer\video` и облачные ASR
- [ ] Не блокировать HTTP-ответ пользователя на весь инференс (afterResponse / queue)

## Критерии приёмки

1. Send → аудио в ленте
2. Transcribe при живом `services/asr` → текст в Dialog
3. ASR down → `error`, UI жив
4. 403 на чужой clip

## Вне скоупа

- Диаризация, SRT, саммари, авто-transcribe при save
