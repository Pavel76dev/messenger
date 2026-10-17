# AGENTS.md

Инструкция для AI-агента в этом репозитории.

## Язык

Отвечай пользователю по-русски, если не попросили иначе.

## SDLS (обязательно)

Фичи ведутся по [`specs/sdls.md`](specs/sdls.md):

`Vibes → PDR → Feature Spec → ADR? → Tasks → Implement → Review`

Не пиши код без Feature Spec (`ready`) и Tasks — кроме явного «без спеки» от пользователя.

## Источник правды

1. `specs/sdls.md` — процесс
2. `specs/product.md` — продукт
3. `specs/vibes/` — продуктовое ощущение и рамки
4. `specs/architecture.md` — устройство кода
5. `specs/pdr/` — продуктовые решения по фиче
6. `specs/features/*.md` — требования и приёмка
7. `specs/tasks/` — атомарные шаги агента
8. `specs/adr/` — архитектурные решения

## Скилы

| Задача | Скил |
|--------|------|
| Старт / ведение цикла SDLS | `.cursor/skills/sdls-flow/SKILL.md` |
| Vibes / PDR / Feature / Tasks / ADR | `.cursor/skills/write-spec/SKILL.md` |
| Реализация по tasks | `.cursor/skills/implement-from-spec/SKILL.md` |
| Проверка готовности | `.cursor/skills/review-against-spec/SKILL.md` |

## Команды

```bash
# frontend (корень проекта)
npm install && npm run dev

# backend / API — через OpenServer (http://messenger)
composer install
php artisan migrate --seed

# websockets (отдельный процесс)
php artisan websockets:serve

# ASR (feature 014) — один раз поставить venv; HTTP-демон НЕ нужен
cd services/asr
python -m venv .venv
.venv\Scripts\python.exe -m pip install -r requirements.txt
# Laravel сам вызывает: .venv/.../python cli.py <файл>
```

## ИИ-чат (api-llm / gate)

Фича **012** (`specs/features/012-ai-chat.md`, ADR-004).

- Локально: `LLM_API_URL=http://127.0.0.1:8050`, `LLM_TARGET=local`; сервис `C:\OpenServer\api-llm`.
- Smoke: `python scripts/probe_ai_llm.py`
- Прод (`__nr_gate/`): reverse SSH туннель VPS `:18050` → ПК `:8050`.

**Полная инструкция для следующего агента (деплой, туннель, поломки):**  
`C:\OpenServer\nr\gate-nr.local\AGENT_AI_LLM.md`

## Аудио-клипы + ASR

Фичи **013** / **014** (`specs/features/013-audio-clips.md`, `014-audio-transcription.md`, ADR-006).

- ASR **внутри** приложения (`services/asr/cli.py`), LLM — снаружи (`api-llm`)
- `python run.py` **не нужен**; модель локально в `services/asr/models/`

**Полная инструкция для следующего агента (setup, поломки, деплой-заметки):**  
[`AGENT_AUDIO_ASR.md`](AGENT_AUDIO_ASR.md)

## Ограничения

- Не реализуй фичи со `status: draft`.
- Frontend: React + Redux Toolkit + MUI; не подключай UI-библиотеки без спеки.
- Backend: Laravel API + Sanctum Bearer.
- Realtime: WebSocket + polling fallback (ADR-002).
- Реакции: нативные эмодзи браузера (не CDN-иконки).
- Минимальный дифф; без рефакторинга «заодно».
