# ADR-001: Sanctum Bearer tokens и polling

- **Статус:** accepted
- **Дата:** 2026-08-29
- **Контекст:** раздельный SPA (Vite) и Laravel API

## Контекст

Нужна простая auth для SPA без усложнения CORS cookie/`Sanctum stateful`, плюс доставка сообщений без WebSocket на первом этапе.

## Решение

1. **Auth:** Laravel Sanctum personal access tokens, клиент шлёт `Authorization: Bearer …`.
2. **Realtime MVP:** HTTP polling `GET .../messages?after_id=` каждые ~3 секунды для активного диалога.

## Альтернативы

1. Cookie SPA Sanctum — сложнее CORS/CSRF при раздельных origin.
2. JWT (tymon/jwt-auth) — лишняя зависимость при наличии Sanctum.
3. WebSocket (Reverb/Pusher) — лучше UX, но тяжелее для первого каркаса на PHP 8.1.

## Последствия

- Плюсы: простой локальный старт, явный API-контракт.
- Минусы: polling даёт задержку и лишнюю нагрузку; позже заменить/дополнить WebSocket (отдельная фича).
