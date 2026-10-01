---
id: 010-group-calls
title: PDR — групповые звонки
status: accepted
created: 2026-09-24
related_vibes: specs/vibes/001-messenger.md
related_feature: specs/features/010-group-calls.md
---

# PDR: Групповые звонки

## Проблема

Звонки есть только в 1:1; в группах нужна общая комната с несколькими участниками.

## Решение

Тот же `calls` + LiveKit room; старт из group conversation; `call.incoming` /
`call.started` на `conversation.{id}`; участники join через accept/join + token;
в UI — roster участников в звонке.

## Скоуп

### In

- `POST /api/conversations/{id}/calls` для `group`
- Join активного группового звонка участником
- Echo на conversation channel для группы
- Roster в CallOverlay

### Out

- Роли модератора, mute others, waiting room
- Screen share (011)

## Пользовательские потоки

1. Участник группы стартует звонок → остальные видят баннер/overlay «идёт звонок» / incoming.
2. Другой участник присоединяется → появляется в roster и в SFU.
3. End от любого участника завершает звонок для всех (MVP).

## Решения и компромиссы

| Вопрос | Решение | Почему |
|--------|---------|--------|
| Кто может end | любой участник | проще MVP |
| Лимит | ≤ members группы (до 20) | product scale |

## Готовность к Feature Spec

- [x] Скоуп согласован
- [x] Out of scope ясен
- [x] Нет блокирующих открытых вопросов

## Связи

- Depends: 009
- Feature: `specs/features/010-group-calls.md`
- Tasks: `specs/tasks/010-group-calls.md`
