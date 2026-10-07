---
id: 012-ai-chat
title: Tasks — Чат с ИИ
status: done
created: 2026-10-07
feature: specs/features/012-ai-chat.md
---

# Tasks: Чат с ИИ

Атомарные шаги для агента. Один task = один проверяемый результат.

## Порядок

| # | Task | DoD (готово когда) | Status |
|---|------|--------------------|--------|
| 1 | Config + .env.example | `config/llm.php`, ключи в `.env.example` | done |
| 2 | AiBot + seeder | Бот upsert; `ensureConversation` | done |
| 3 | LlmClient + GenerateAiReply | HTTP chat; сообщение бота + broadcast | done |
| 4 | MessageController + Auth/Conversations hooks | Триггер + автодиалог | done |
| 5 | UI «Чат с ИИ» | Кнопка в NewChatDialog | done |
| 6 | Review статусов | Feature/tasks → implemented | done |

## Детали

### T1 — Config

- **Делает:** `config/llm.php`; блок в `.env.example`; опционально запись в `architecture.md` одной строкой.
- **Файлы:** `config/llm.php`, `.env.example`
- **Не делать:** коммит реального `.env` с секретами
- **Проверка:** `config('llm.api_url')` читается

### T2 — AiBot + seeder

- **Делает:** сервис нахождения/создания бота и 1:1; seeder вызывает upsert бота (+ ensure для Alice/Bob)
- **Файлы:** `app/Services/AiBot.php`, `database/seeders/DatabaseSeeder.php`
- **Проверка:** после seed есть user с `LLM_BOT_EMAIL`

### T3 — LlmClient + Job

- **Делает:** HTTP к `/api/chat`; job строит history, пишет ответ или ошибку, `SafeBroadcast::now`
- **Файлы:** `app/Services/LlmClient.php`, `app/Jobs/GenerateAiReply.php`
- **Проверка:** при недоступном LLM — сообщение с ошибкой, без exception наружу

### T4 — Hooks

- **Делает:** после store сообщения — `afterResponse` job если peer=bot; `ensure` в `ConversationController::index` и `AuthController::register`
- **Файлы:** `MessageController.php`, `ConversationController.php`, `AuthController.php`
- **Проверка:** человек↔человек не диспатчит job

### T5 — UI

- **Делает:** кнопка «Чат с ИИ» в NewChatDialog (direct): ищет бота в results или отдельный `GET /api/users?q=` / по имени из списка; `createConversation({user_id})`
- **Файлы:** `NewChatDialog.jsx`
- **Проверка:** клик открывает/создаёт диалог с ботом

### T6 — Статусы

- **Делает:** отметить tasks done; feature `implemented`; пункт в `product.md`
- **Проверка:** review-against-spec

## Definition of Done (фича целиком)

- [x] Все Must из Feature Spec
- [x] Критерии приёмки выполнены (код готов; e2e зависит от MySQL OpenServer + api-llm)
- [x] `status` фичи → `implemented`
- [x] Review-against-spec без критичных gaps
