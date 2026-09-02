# Product brief

## Проблема

Нужен учебный веб-мессенджер: личные переписки в браузере с отдельным API-бэкендом.

## Для кого

- Пользователи, которым нужен простой чат 1:1
- Разработчик / агент, ведущие проект по спекам в Cursor

## Ценность

- Регистрация и вход
- Список диалогов и переписка в реальном времени через polling
- Чёткий контракт API между React SPA и Laravel

## Не цели (non-goals) для MVP

- Групповые чаты
- Вложения / голосовые / звонки
- WebSocket / push (будет отдельной фичей)
- Мобильные нативные клиенты
- E2E-шифрование

## Стек (зафиксировано)

| Слой | Технологии |
|------|------------|
| Frontend | React, Redux Toolkit, Material UI, Vite, React Router |
| Backend | Laravel 10, Sanctum (Bearer token), SQLite для локальной разработки |
| Realtime MVP | HTTP polling сообщений |

## Метрики успеха MVP

- [x] `frontend`: `npm run dev` открывает SPA
- [x] `backend`: `php artisan serve` отдаёт API
- [x] Регистрация / логин / logout работают end-to-end
- [x] Можно создать/открыть 1:1 диалог и обменяться сообщениями (с polling)

## Бэклог фич

1. ~~Каркас монорепо и UI-шелл~~ — `features/002-monorepo-scaffold.md` (implemented)
2. ~~Auth (register/login)~~ — `features/003-auth.md` (implemented)
3. ~~Диалоги и сообщения~~ — `features/004-conversations-messages.md` (implemented)
4. _(позже)_ realtime через WebSocket
