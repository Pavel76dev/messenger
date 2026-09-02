---
id: 002-monorepo-scaffold
title: Каркас монорепо мессенджера
status: implemented
owner: team
created: 2026-08-29
---

# 002 — Каркас монорепо мессенджера

## Контекст

Нужна рабочая заготовка: React/Redux/MUI фронт и Laravel API, чтобы дальше наращивать auth и чат.

## Цель

Репозиторий разделён на `frontend/` и `backend/`, оба поднимаются локально; на фронте — UI-шелл мессенджера (без полного API-flow, если 003/004 ещё не готовы).

## User stories

- Как разработчик, я клонирую репо и по README поднимаю оба сервиса.
- Как агент, я вижу спеки и структуру каталогов и не смешиваю слои.

## Требования

### Must

- [ ] Каталог `frontend/` — Vite + React + Redux Toolkit + MUI + React Router
- [ ] Каталог `backend/` — Laravel 10 + Sanctum + SQLite + CORS под Vite
- [ ] Корневой README с командами запуска
- [ ] Обновлены `specs/product.md`, `specs/architecture.md`, `AGENTS.md`, rules
- [ ] На фронте маршруты: `/login`, `/register`, `/` (messenger shell)
- [ ] Messenger shell: боковая колонка диалогов + область сообщений + поле ввода (MUI), можно с mock/empty state
- [ ] API-роуты и миграции под модель MVP объявлены (реализация логики — в 003/004, здесь допустимы рабочие заглушки или полная реализация)

### Should

- [ ] ADR про Sanctum tokens + polling
- [ ] `.env.example` у backend с SQLite

### Must not

- [ ] Оставлять старый vanilla `src/` в корне как основное приложение
- [ ] Группы, вложения, WebSocket в этом PR-скоупе

## UX / UI

- MUI layout: `Drawer`/`List` слева, thread справа
- Пустые состояния: «Нет диалогов», «Выберите чат»
- Тема светлая, компактная под чат (не лендинг)

## Технические заметки

- Удалить или перенести корневой Vite vanilla в `frontend/`
- PHP 8.1 → Laravel **10** (не 11)
- Frontend proxy или `VITE_API_URL=http://127.0.0.1:8000`

## Критерии приёмки

1. `cd frontend && npm install && npm run build` — успех
2. `cd backend && composer install && php artisan migrate` — успех
3. UI shell открывается через `npm run dev`
4. Спеки 002+ отражают фактическую структуру

## Вне скоупа

- Полный E2E-чат (фича 004)
- Деплой
