---
id: 014-audio-transcription
title: PDR — отправка из библиотеки + транскрибация клипов
status: draft
created: 2026-10-14
related_vibes: specs/vibes/001-messenger.md
related_feature: specs/features/014-audio-transcription.md
---

# PDR: Отправка из библиотеки + транскрибация

## Проблема

В библиотеке клипов нельзя отправить запись в открытый чат. Нет распознавания речи
и просмотра текста после транскрибации.

## Решение (одним абзацем)

В списке сохранённых: Send в текущий чат; кнопка «Транскрибировать»; после успеха —
иконка просмотра текста. ASR — локальный **faster-whisper** (как `C:\OpenServer\video`),
через отдельный HTTP-сервис; Laravel ставит job, сохраняет текст у `audio_clips`.

## Скоуп

### In

- Send клипа из library в текущий `conversationId` (как вложение 006)
- Поля/статус транскрипта у клипа: `idle|pending|done|error` + текст
- `POST /api/audio-clips/{id}/transcribe` → queue/job → вызов Whisper API
- UI: иконка транскрибации; после done — иконка просмотра (Dialog с текстом)
- Переиспользование паттерна video (`faster-whisper>=1.1.0`) или тонкий клиент к уже
  запущенному video-сервису на `:8020`

### Out

- Диаризация говорящих, SRT в UI, саммари через Ollama
- Облачный ASR (Google/Yandex/Deepgram) как основной движок
- Редактирование транскрипта
- Авто-транскрибация при сохранении клипа

## Решения и компромиссы

| Вопрос | Решение | Почему |
|--------|---------|--------|
| ASR движок | faster-whisper (MIT) | Уже есть в video; RU; коммерчески ок |
| Где крутится | Отдельный Python HTTP (reuse video или `api-asr`) | Laravel не тянет CT2; как api-llm |
| Связь | Laravel → `ASR_API_URL` upload/poll или sync для коротких клипов | Короткие фрагменты ≤5 мин |
| Хранение текста | Колонки на `audio_clips` | Минимум сущностей |

## Риски

- CPU-инференс на weak машине — долгий pending; нужен статус в UI
- Video-сервис заточен под большие файлы/jobs — для клипов лучше узкий endpoint
  `POST /api/transcribe` → `{ text }` или job+poll
- Прод (gate): reverse SSH tunnel по аналогии с LLM

## Готовность к Feature Spec

- [ ] Скоуп согласован (в т.ч.: новый микросервис vs вызов `video:8020`)
- [ ] Out ясен
- [ ] Нет блокеров

## Связи

- Extends: `013-audio-clips`
- Feature: `specs/features/014-audio-transcription.md`
- ADR (proposed): отдельный ASR HTTP, не в PHP
