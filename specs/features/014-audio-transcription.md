---
id: 014-audio-transcription
title: Библиотека — отправка + транскрибация клипов
status: draft
owner: team
created: 2026-10-14
---

# 014 — Отправка из библиотеки + транскрибация

## Контекст

PDR: `specs/pdr/014-audio-transcription.md`. База: `013-audio-clips`.
Ориентир ASR: `C:\OpenServer\video` (faster-whisper).

## Цель

Из списка сохранённых записей: отправить в текущий чат; запустить транскрибацию;
просмотреть готовый текст.

## User stories

- Как пользователь, я из библиотеки отправляю клип в открытый чат.
- Как пользователь, я нажимаю «Транскрибировать» и жду статус.
- Как пользователь, после успеха я открываю текст транскрипта.

## Требования

### Must

- [ ] Library: иконка «Отправить в чат» (нужен открытый `conversationId`)
- [ ] Send → сообщение с `files[]` из файла клипа (или server-side copy) через API 006
- [ ] Library: иконка транскрибации; при `pending` — индикация
- [ ] `POST /api/audio-clips/{id}/transcribe` (владелец) → ставит задачу
- [ ] Клип в API: `transcript_status`, `transcript_text`, `transcript_error?`
- [ ] После `done` — иконка просмотра → Dialog с текстом
- [ ] ASR: faster-whisper локально (MIT), язык `ru`; конфиг `ASR_*` / reuse video URL
- [ ] Ошибки ASR на русском в UI

### Should

- [ ] Повторная транскрибация перезаписывает текст
- [ ] Poll статуса клипа пока library открыта и есть pending

### Must not

- [ ] Не слать аудио во внешние облачные ASR
- [ ] Не блокировать HTTP send сообщения на весь инференс (async job)
- [ ] Не требовать диаризацию/SRT в MVP

## UX / UI

- Строка клипа: Play | Send | Transcribe | View(transcript) | Delete
- View disabled/скрыт пока не `done`
- Transcribe disabled при `pending`

## Технические заметки

- Миграция: `transcript_status` (string), `transcript_text` (text nullable),
  `transcript_error` (text nullable), опц. `transcript_job_id`
- Laravel Job → HTTP к ASR-сервису (файл с диска)
- Вариант A: вызвать существующий `video` `:8020` (`/api/upload` + poll)
- Вариант B: тонкий `api-asr` только для коротких клипов (предпочтительно позже)

## Критерии приёмки

1. Send из library → в ленте аудио у обоих участников
2. Transcribe → pending → done; текст в Dialog
3. Чужой клип: 403 на transcribe/send-server
4. ASR down → status error, UI не падает

## Вне скоупа

- Правка текста, экспорт SRT, саммари, облачный ASR
