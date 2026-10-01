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

- Мобильные нативные клиенты (веб-звонки — да; нативные SDK LiveKit — позже)
- E2E-шифрование
- Роли/админы в группах, кик после создания
- Запись звонков, PSTN

## Стек (зафиксировано)

| Слой | Технологии |
|------|------------|
| Frontend | React, Redux Toolkit, Material UI, Vite, React Router |
| Backend | Laravel 10, Sanctum (Bearer token), MySQL (OpenServer) |
| Realtime | WebSocket (laravel-websockets) + HTTP polling fallback |

## Метрики успеха MVP

- [x] `frontend`: `npm run dev` открывает SPA
- [x] `backend`: API на `http://messenger`
- [x] Регистрация / логин / logout работают end-to-end
- [x] Можно создать/открыть 1:1 диалог и обменяться сообщениями
- [x] Реакции на сообщения + realtime (см. feature 007)
- [x] Групповые чаты (см. feature 008)

## Бэклог фич

1. ~~Каркас монорепо и UI-шелл~~ — `features/002-monorepo-scaffold.md` (implemented)
2. ~~Auth (register/login)~~ — `features/003-auth.md` (implemented)
3. ~~Диалоги и сообщения~~ — `features/004-conversations-messages.md` (implemented)
4. ~~Профиль пользователя~~ — `features/005-user-profile.md` (implemented)
5. ~~Вложения к сообщениям~~ — `features/006-message-attachments.md` (implemented)
6. ~~Реакции + WebSocket realtime~~ — `features/007-message-reactions.md` (implemented)
7. ~~Групповые чаты~~ — `features/008-group-chats.md` (implemented)
8. ~~Звонки 1:1 (LiveKit)~~ — `features/009-calls-foundation.md` (implemented)
9. ~~Групповые звонки~~ — `features/010-group-calls.md` (implemented)
10. ~~Демонстрация экрана~~ — `features/011-screen-share.md` (implemented)

Процесс новых фич: [`sdls.md`](./sdls.md).
