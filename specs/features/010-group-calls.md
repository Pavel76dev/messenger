---
id: 010-group-calls
title: Групповые звонки
status: implemented
owner: team
created: 2026-09-24
---

# 010 — Групповые звонки

## Контекст

Расширение 009 на `conversation.type = group`. ADR-003.

## Цель

Участники группы звонят в одну LiveKit-комнату и видят список кто в звонке.

## User stories

- Как участник группы, я начинаю групповой звонок.
- Как участник, я присоединяюсь к активному звонку.
- Как участник, я вижу кто сейчас в звонке.

## Требования

### Must

- [x] `POST /api/conversations/{id}/calls` работает для `group` (не только direct)
- [x] `POST /api/calls/{id}/join` — участник conversation входит в `ringing`/`active` group call → status `active`
- [x] Broadcast group call events на `conversation.{id}` (+ personal incoming опционально)
- [x] Сериализация call: `participants: [{id, name, avatar_url}]` кто join'ился (таблица `call_participants` или аналог)
- [x] UI: кнопки звонка в group шапке; «Присоединиться»; roster в overlay

### Should

- [x] Баннер «Идёт звонок» в ленте группы при active call

### Must not

- [x] Не ломать 1:1 из 009

## Критерии приёмки

1. Звонок из группы виден другим участникам без F5 (WS)
2. Второй участник join → в roster и в медиа
3. End завершает для всех
4. Неучастник группы → 403

## Вне скоупа

- Screen share, kick из звонка, роли
