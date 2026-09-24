---
id: 007-message-reactions
title: Реакции на сообщения и realtime
status: implemented
owner: team
created: 2026-09-09
---

# 007 — Реакции на сообщения и realtime

## Контекст

После базового чата нужны быстрые реакции и доставка обновлений без F5.
См. PDR: `specs/pdr/007-message-reactions.md`.

## Цель

Пользователь ставит/снимает реакцию эмодзи; оба участника видят актуальные счётчики
через WebSocket (или polling-fallback).

## User stories

- Как участник диалога, я ставлю реакцию на сообщение из фиксированного набора.
- Как участник, я снимаю свою реакцию повторным кликом.
- Как собеседник, я вижу чужие реакции без перезагрузки страницы.

## Требования

### Must

- [x] Таблица `message_reactions`: `message_id`, `user_id`, `reaction_key`, unique `(message_id, user_id, reaction_key)`
- [x] Whitelist ключей в `config/message_reactions.php` (emoji — нативные)
- [x] `POST /api/messages/{message}/reactions` — `{ reaction_key }` toggle; только участник диалога
- [x] Сериализация сообщений включает `reactions[]` с `emoji`, `count`, `reacted_by_me`, `user_ids`
- [x] UI: picker + чипы под сообщением
- [x] Private channel `conversation.{id}`; события `message.created`, `message.reaction.updated`
- [x] Echo-клиент; polling silent fallback

### Should

- [x] SafeBroadcast: ошибка WS не даёт 500 на toggle/send
- [x] Автоскролл только при новом сообщении, не при обновлении реакций

### Must not

- [x] Внешние CDN-иконки реакций (Twemoji и т.п.)
- [x] Реакции от пользователей вне диалога

## UX / UI

- Кнопка «добавить реакцию» у пузыря
- Popper с набором эмодзи
- Активная «моя» реакция визуально выделена

## Технические заметки

- Backend: `MessageReaction`, `MessageReactionController`, events, `SafeBroadcast`
- Frontend: `messagesSlice` (`toggleMessageReaction`, `applyRealtimeMessage`), `echo.js`
- Запуск WS: `php artisan websockets:serve` (или `npm run soketi`)
- Env: `BROADCAST_DRIVER=pusher`, `PUSHER_*`, `VITE_PUSHER_*`

## Критерии приёмки

1. Toggle реакции сохраняется в БД и отражается в ответе API
2. Собеседник видит реакцию через WS или silent poll без F5
3. Нативные эмодзи в UI (не `<img>` с CDN)
4. Посторонний получает 403 на чужой диалог/сообщение

## Вне скоупа

- Кастомные эмодзи, анимации, «кто поставил» модалкой
- Laravel Reverb / облачный Pusher
