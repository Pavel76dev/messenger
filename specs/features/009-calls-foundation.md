---
id: 009-calls-foundation
title: Основа звонков 1:1 (LiveKit)
status: implemented
owner: team
created: 2026-09-24
---

# 009 — Основа звонков 1:1

## Контекст

PDR: `specs/pdr/009-calls-foundation.md`. ADR: `specs/adr/003-livekit-sfu.md`.

## Цель

Участник личного чата может начать аудио- или видеозвонок; собеседник принимает
или отклоняет; медиа идёт через LiveKit.

## User stories

- Как пользователь, я звоню собеседнику из direct-чата голосом или с видео.
- Как адресат, я вижу входящий звонок и могу принять / отклонить.
- Как участник, я могу завершить звонок и отключить микрофон / камеру.

## Требования

### Must

- [x] Миграция `calls`: `conversation_id`, `created_by`, `room_name`, `media_type` (`audio`|`video`), `status` (`ringing`|`active`|`ended`|`rejected`), timestamps
- [x] `POST /api/conversations/{id}/calls` — только участник; только `direct` в этой фиче; body `{ media_type }`; 409 если уже есть ringing/active
- [x] `POST /api/calls/{id}/accept|reject|end` — только участник conversation
- [x] `POST /api/calls/{id}/token` — LiveKit JWT; 403 чужим; 422 если call не active/ringing (caller) или не active (после accept)
- [x] Echo: `call.incoming` на `user.{peerId}`; `call.accepted` / `call.rejected` / `call.ended` на оба `user.*` участников
- [x] Config/env: `LIVEKIT_URL`, `LIVEKIT_API_KEY`, `LIVEKIT_API_SECRET`; `VITE_LIVEKIT_URL`
- [x] UI: кнопки звонка в шапке direct; overlay ringing / in-call; mute / camera toggle / hangup
- [x] Docker Compose пример LiveKit в `docker/livekit/`

### Should

- [x] Понятная ошибка, если LiveKit URL не настроен или connect fail

### Must not

- [x] Не слать медиа через Echo/Laravel
- [x] Не ломать текстовый чат и polling fallback

## UX / UI

- Ringing: имя собеседника, принять / отклонить
- In-call: локальное + remote video (для video), controls снизу
- На mobile — полноэкранный overlay

## Технические заметки

- Room name: `call-{id}` или UUID, уникальный
- Token identity = `user-{id}`, name = display name
- SafeBroadcast для событий

## Критерии приёмки

1. A звонит B в direct → B видит incoming без F5 (при живом WS)
2. Accept → оба в комнате, слышат/видят друг друга при запущенном LiveKit
3. Reject / end → статус ended/rejected, overlay закрыт
4. Неучастник conversation → 403 на calls/token

## Вне скоупа

- Группы, screen share, запись
