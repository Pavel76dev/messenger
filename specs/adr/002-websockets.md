# ADR-002: WebSocket realtime (laravel-websockets + Echo)

- **Статус:** accepted
- **Дата:** 2026-09-09

## Контекст

Polling с `after_id` не видит обновления реакций на уже загруженных сообщениях. Нужен realtime для сообщений и реакций.

## Решение

1. `beyondcode/laravel-websockets` (Pusher-совместимый сервер) + `pusher/pusher-php-server`
2. Laravel Echo + pusher-js на фронте
3. Private channel `conversation.{id}` (участники диалога)
4. События: `message.created`, `message.reaction.updated`
5. Polling остаётся fallback (реже при активном WS)

## Последствия

- Нужен отдельный процесс: `php artisan websockets:serve`
- Пакет abandoned, но работает на Laravel 10 / PHP 8.1; позже можно заменить на Reverb (Laravel 11+)
