---
id: 003-auth
title: Регистрация и вход
status: implemented
owner: team
created: 2026-08-29
---

# 003 — Регистрация и вход

## Контекст

Без учёток нельзя вести личные переписки.

## Цель

Пользователь регистрируется, входит, получает Bearer token и попадает в мессенджер; logout сбрасывает сессию.

## User stories

- Как новый пользователь, я регистрируюсь именем, email и паролем.
- Как существующий, я вхожу и вижу интерфейс чата.
- Как пользователь, я выхожу и снова вижу экран логина.

## Требования

### Must

- [ ] `POST /api/register` — name, email, password, password_confirmation → `{ token, user }`
- [ ] `POST /api/login` — email, password → `{ token, user }`
- [ ] `POST /api/logout` — auth:sanctum, отзыв текущего токена
- [ ] `GET /api/me` — текущий user
- [ ] Страницы Login / Register на MUI
- [ ] Redux slice `auth`: user, token, status, error
- [ ] Token в `localStorage` (`messenger_token`)
- [ ] Protected route: `/` только с token, иначе redirect `/login`

### Should

- [ ] Валидационные ошибки API показываются под полями / алертом

### Must not

- [ ] Cookie-based SPA auth в MVP
- [ ] OAuth / соцсети

## Критерии приёмки

1. Регистрация создаёт user и возвращает token
2. Логин с неверным паролем → 422/401 и сообщение на UI
3. После логина `GET /api/me` с Bearer работает
4. Logout удаляет token на клиенте и на сервере

## Вне скоупа

- Email verification, reset password
