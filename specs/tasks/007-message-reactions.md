---
id: 007-message-reactions
title: Tasks — реакции и realtime
status: done
created: 2026-09-09
feature: specs/features/007-message-reactions.md
---

# Tasks: Реакции на сообщения и realtime

## Порядок

| # | Task | DoD | Status |
|---|------|-----|--------|
| 1 | Миграция + модель реакций | таблица и Eloquent-связь | done |
| 2 | Config whitelist эмодзи | `config/message_reactions.php` без CDN | done |
| 3 | API toggle + сериализация | POST toggle, reactions в JSON | done |
| 4 | Broadcasting + events | private channel, 2 events, SafeBroadcast | done |
| 5 | Frontend UI реакций | picker + чипы, нативные emoji | done |
| 6 | Echo + polling fallback | подписка на канал, silent poll | done |
| 7 | Env / README / ADR | документация запуска WS | done |

## Детали

### T1 — Миграция + модель

- **Делает:** `message_reactions`, `MessageReaction`, `Message::reactions()`
- **Проверка:** `php artisan migrate`

### T2 — Config

- **Делает:** whitelist `like/love/laugh/surprised/sad/angry` + emoji
- **Не делать:** `icon_url` / Twemoji CDN

### T3 — API

- **Делает:** `MessageReactionController@toggle`, serialize reactions
- **Проверка:** toggle 200, 403 для чужого диалога

### T4 — Broadcast

- **Делает:** `MessageCreated`, `MessageReactionUpdated`, BroadcastServiceProvider, channels
- **Проверка:** событие уходит при живом `websockets:serve`

### T5 — UI

- **Делает:** `MessageReactions` в `MessageThread`, slice thunk
- **Проверка:** клик ставит/снимает без F5 у автора

### T6 — Echo

- **Делает:** `resources/js/shared/realtime/echo.js`, подписка в `MessengerPage`
- **Проверка:** второй клиент видит реакцию

### T7 — Docs

- **Делает:** `.env.example`, README, ADR-002

## Definition of Done (фича целиком)

- [x] Все Must из Feature Spec
- [x] Критерии приёмки выполнены
- [x] `status` фичи → `implemented`
- [x] Пример SDLS зафиксирован в `specs/sdls.md`
