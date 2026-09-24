---
id: 007-message-reactions
title: PDR — реакции на сообщения и realtime
status: accepted
created: 2026-09-09
related_vibes: specs/vibes/001-messenger.md
related_feature: specs/features/007-message-reactions.md
---

# PDR: Реакции на сообщения + realtime

## Проблема

В ленте нельзя быстро отреагировать на сообщение. Обновления существующих сообщений
(реакции) не видны через `after_id`-polling без перезагрузки. Нужен realtime.

## Решение (одним абзацем)

Фиксированный набор реакций (нативные эмодзи), toggle API, агрегаты в JSON сообщения;
доставка изменений через private WebSocket-канал диалога + polling как fallback.

## Скоуп

### In

- Toggle реакции участником диалога
- Агрегаты: `reaction_key`, `emoji`, `count`, `reacted_by_me`, `user_ids`
- UI: чипы + picker под пузырём
- Broadcast: `message.created`, `message.reaction.updated`
- Silent polling fallback

### Out

- Произвольные кастомные эмодзи / стикеры
- Список «кто поставил» в отдельном модальном окне (можно позже)
- Reverb / облачный Pusher (локальный laravel-websockets / Soketi)
- Редактирование/удаление сообщений

## Пользовательские потоки

1. Пользователь открывает picker → выбирает эмодзи → реакция появляется у него сразу.
2. Собеседник видит обновление через WS (или ≤ fallback-poll).
3. Повторный клик по своей реакции снимает её (toggle).
4. Новое сообщение уходит в канал `conversation.{id}`.

## Решения и компромиссы

| Вопрос | Решение | Почему |
|--------|---------|--------|
| Иконки | Нативные эмодзи ОС/браузера | Без CDN, проще офлайн |
| Набор реакций | whitelist в `config/message_reactions.php` | Предсказуемый API |
| Realtime | laravel-websockets + Echo/pusher-js | Laravel 10, локальный OSPanel |
| Сбой WS | SafeBroadcast + polling | API реакций не должен падать 500 |
| App id Pusher | числовой `1` | Совместимость с laravel-websockets |

## Риски

- Abandoned `beyondcode/laravel-websockets` — позже миграция на Reverb (L11+)
- `reacted_by_me` user-specific → в WS отдаём `user_ids`, клиент пересчитывает

## Готовность к Feature Spec

- [x] Скоуп согласован
- [x] Out of scope ясен
- [x] Нет блокирующих открытых вопросов

## Связи

- Vibes: `specs/vibes/001-messenger.md`
- Feature Spec: `specs/features/007-message-reactions.md`
- ADR: `specs/adr/002-websockets.md`
- Tasks: `specs/tasks/007-message-reactions.md`
