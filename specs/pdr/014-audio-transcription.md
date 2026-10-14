---
id: 014-audio-transcription
title: PDR — отправка из библиотеки + транскрибация (ASR в репо)
status: accepted
created: 2026-10-14
related_vibes: specs/vibes/001-messenger.md
related_feature: specs/features/014-audio-transcription.md
---

# PDR: Отправка из библиотеки + транскрибация

## Проблема

Из библиотеки нельзя отправить клип в чат; нет локальной транскрибации и просмотра текста.

## Решение

В library: Send / Transcribe / View. ASR — внутри репо (`services/asr/cli.py` + Job),
без внешнего HTTP. LLM по-прежнему внешний api-llm.

## Скоуп

### In

- Send из library в текущий чат
- Транскрибация клипа (pending → done/error), просмотр текста
- Сервис `services/asr` + Job + поля на `audio_clips`

### Out

- Диаризация, SRT UI, Ollama-саммари, облачный ASR, зависимость от video-проекта

## Готовность к Feature Spec

- [x] Скоуп согласован (ASR полностью в messenger)
- [x] Out ясен
- [x] Нет блокеров

## Связи

- ADR: `specs/adr/006-asr-service-in-repo.md`
- Feature: `specs/features/014-audio-transcription.md`
- Tasks: `specs/tasks/014-audio-transcription.md`
