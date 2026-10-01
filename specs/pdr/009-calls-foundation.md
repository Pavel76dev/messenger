---
id: 009-calls-foundation
title: PDR — основа звонков (1:1 аудио/видео)
status: accepted
created: 2026-09-24
related_vibes: specs/vibes/001-messenger.md
related_feature: specs/features/009-calls-foundation.md
---

# PDR: Основа звонков (1:1)

## Проблема

В мессенджере нельзя позвонить собеседнику — только текст. Нужен голосовой и
видеозвонок в личном чате с realtime-приглашением.

## Решение (одним абзацем)

Self-hosted LiveKit SFU для медиа; Laravel API создаёт `call`, рассылает invite
через Echo на `user.{id}`, выдаёт JWT комнаты; React overlay для ringing / in-call.

## Скоуп

### In

- Таблица `calls` + API start / Accept / reject / end / token
- 1:1 аудио и видео
- Echo: `call.incoming`, `call.accepted`, `call.rejected`, `call.ended`
- Docker Compose LiveKit (+ документация coturn)
- UI: кнопки в шапке direct-чата, overlay звонка

### Out

- Групповые звонки (010)
- Screen share (011)
- Запись, PSTN, E2E media encryption
- Нативные мобильные приложения

## Пользовательские потоки

1. A в direct-чате нажимает «Позвонить» / «Видео» → B видит входящий overlay.
2. B принимает → оба получают token и подключаются к LiveKit room.
3. B отклоняет или A/B завершает → `call.ended`, комната закрыта для новых join.

## Решения и компромиссы

| Вопрос | Решение | Почему |
|--------|---------|--------|
| Медиа | LiveKit SFU | ADR-003 |
| Сигналинг | Echo + REST | уже есть в проекте |
| Один активный звонок на conversation | да | упрощает UX MVP |

## Риски

- Без запущенного LiveKit connect упадёт на клиенте — показать понятную ошибку.
- Без HTTPS `getUserMedia` не работает на реальных устройствах.

## Готовность к Feature Spec

- [x] Скоуп согласован
- [x] Out of scope ясен
- [x] Нет блокирующих открытых вопросов

## Связи

- Vibes: `specs/vibes/001-messenger.md`
- Feature: `specs/features/009-calls-foundation.md`
- ADR: `specs/adr/003-livekit-sfu.md`
- Tasks: `specs/tasks/009-calls-foundation.md`
