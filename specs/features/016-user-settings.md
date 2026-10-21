---
id: 016-user-settings
title: Настройки пользователя (промпт задач)
status: implemented
owner: team
created: 2026-10-17
---

# 016 — Настройки пользователя

## Цель

У каждого пользователя есть панель настроек; можно изменить промпт извлечения задач. Архитектура допускает новые ключи без новой таблицы.

## Must

- [x] Таблица `user_settings`: `user_id`, `key`, `value` (text), unique(user_id, key)
- [x] Реестр в `config/user_settings.php`: key, type (`text`|`textarea`|`checkbox`), group, label, description, default
- [x] UI для людей: только textarea «пожелания»; сырой prompt не показывается
- [x] `TasksExtractPromptBuilder`: JSON-формат + игнор болтовни всегда в коде; пожелания пользователя опциональны
- [x] `GET /api/me/settings` → `{ definitions, values, overridden }`
- [x] `PATCH /api/me/settings` `{ values }` — только ключи из реестра; `null` → сброс
- [x] Extract читает настройки владельца клипа
- [x] UI `/settings` + ссылки из профиля/шапки

## Must not

- [x] Не хранить секреты LLM в user_settings
- [x] Не принимать произвольные ключи вне реестра

## Критерии приёмки

1. Пользователь меняет system prompt → extract использует новый текст
2. Сброс → снова дефолт из конфига
3. Чужие settings недоступны (только `/me`)
