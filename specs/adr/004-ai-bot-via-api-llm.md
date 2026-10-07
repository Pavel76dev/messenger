# ADR-004: ИИ-бот как User + прокси в api-llm

- **Статус:** accepted
- **Дата:** 2026-10-07
- **Контекст:** feature `012-ai-chat`

## Контекст

Нужна интеграция локального `api-llm` в мессенджер так, чтобы ответы выглядели как обычные сообщения и работали WebSocket/polling.

## Решение

1. Бот — обычная запись `users` (email из `LLM_BOT_EMAIL`).
2. Диалог — существующий `direct` 1:1; автосоздание через `AiBot::ensureConversation`.
3. Генерация — HTTP к `POST {LLM_API_URL}/api/chat` из Laravel (`LlmClient`).
4. Триггер — после сохранения сообщения пользователя; выполнение `afterResponse`, чтобы send не ждал инференс.
5. Broadcast ответа — `SafeBroadcast::now` (всем участникам, включая автора).

## Альтернативы

1. Отдельная таблица bots / отдельный API `/ai/chat` — лишняя сложность для MVP.
2. Фронт бьёт в api-llm напрямую — утечка токена, нет единой истории в БД мессенджера.
3. Sync job в том же HTTP-запросе — таймауты и плохой UX при 30–60 с инференса.

## Production (gate)

На VPS messenger не ходит в LAN напрямую. Как `conveyor-bridge`: SSH reverse tunnel с домашнего ПК:

`VPS 127.0.0.1:18050` ← tunnel ← `api-llm 127.0.0.1:8050`

`LLM_API_URL=http://127.0.0.1:18050` на сервере. Публичный nginx для LLM не нужен (PHP на том же VPS). Скрипты: `gate-nr.local/scripts/start_llm_reverse_tunnel.py`.

## Последствия

- Плюсы: минимальный дифф, reuse UI/realtime, история в `messages`.
- Минусы / долг: нет typing indicator; нет stream; бот по email (не `is_bot` flag) — достаточно для MVP; на проде нужен живой туннель.
