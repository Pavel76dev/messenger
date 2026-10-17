---
id: 015-transcript-task-lists
title: Список задач из транскрипта (LLM)
status: implemented
owner: team
created: 2026-10-17
---

# 015 — Список задач из транскрипта

## Контекст

Фича 014 даёт `transcript_text` на аудио-клипе. Фича 012 / ADR-004 — доступ к api-llm через `LlmClient`. Нужно связать: из текста клипа получить структурированный список дел и показать его **у этого клипа**. См. PDR `015-transcript-task-lists`.

## Цель

По кнопке пользователь превращает готовый транскрипт в сохранённый список задач (если ИИ узнал пункты); список живёт рядом с исходным аудио в библиотеке — можно открыть и отметить пункты.

## User stories

- Как владелец клипа, я хочу нажать «Сделать список задач» на готовом транскрипте, чтобы не разбирать речь вручную.
- Как пользователь, я хочу видеть задачи **у этой записи** в library и отмечать пункты выполненными.
- Как пользователь, я хочу понять, если в тексте задач не было или LLM недоступен.

## Требования

### Must

- [x] Таблицы: `task_lists` (`user_id`, **`audio_clip_id` NOT NULL**, `title`, `source=audio_clip_transcript`, timestamps) и `task_items` (`task_list_id`, `body`, `position`, `status` `open|done`)
- [x] `POST /api/audio-clips/{id}/extract-tasks` (auth, владелец): только при `transcript_status=done` и непустом тексте → `202` / pending, фон зовёт LLM
- [x] Фон через `LlmClient` → JSON  
  `{ "title": string, "tasks": [ { "text": string } ] }`  
  пусто: `{ "title": "", "tasks": [] }`
- [x] При `tasks.length >= 1` — создать `task_lists` + `task_items`, привязанные к клипу
- [x] При пустом `tasks` — список **не** создавать; статус операции `empty` + русское сообщение
- [x] Ошибка LLM/парсинга → `error` + текст; транскрипт не менять
- [x] API клипа (`GET /api/audio-clips` и/или вложенный ресурс) отдаёт связанные `task_lists` с `items` (или отдельный `GET /api/audio-clips/{id}/task-lists`) — достаточно для отрисовки у строки клипа
- [x] `PATCH /api/task-items/{id}` — `{ status: open|done }` только свой
- [x] `DELETE /api/task-lists/{id}` — удалить список со своими items (клип остаётся)
- [x] UI library: у клипа с `done` — кнопка extract; pending; empty/error текстом; при успехе пункты **под/в раскрытии этого клипа** (checkbox toggle)
- [x] Если у клипа несколько списков — показать все (свежие сверху) у этого клипа

### Should

- [x] Заголовок: LLM `title`, иначе «Задачи» / «Задачи из записи»
- [x] Повторный extract создаёт новый список у того же клипа
- [x] Poll list клипов (или task-lists клипа), пока extract `pending`

### Must not

- [x] Отдельный глобальный экран / кнопка composer «Мои задачи» в v1
- [x] Не звать ASR заново; только `transcript_text`
- [x] Не слать аудио в LLM
- [x] Не блокировать HTTP на весь инференс
- [x] Не создавать список при нуле пунктов

## UX / UI

- Всё в `AudioClipsLibraryDialog` (или дочернем блоке строки клипа)
- Кнопка «Список задач» при `transcript_status=done`
- Pending: спиннер на кнопке/строке
- Успех: блок пунктов под клипом (не отдельный раздел приложения)
- Empty / error: короткий текст у клипа или snackbar
- Язык — русский

## Технические заметки

- `LlmClient` + сервис/команда extract; фон как у ASR (OpenServer-friendly)
- Статус extract: поля на `audio_clips` (`tasks_extract_status`, `tasks_extract_error`) — удобно для poll уже существующего list клипов
- Парсинг JSON из ответа (снять markdown-обёртку при необходимости)
- Sanctum; 403 на чужие clip/list/item
- Frontend: только `features/audioClips`; без новых UI-библиотек

## Критерии приёмки

1. Транскрипт со списком дел → extract → пункты видны **у этого клипа** в library
2. Нейтральный текст → список не создан, UI сообщает empty у клипа
3. api-llm down → error у операции, транскрипт на месте
4. Чужой clip / item → 403
5. Toggle `done` сохраняется после reload library

## Вне скоупа

- Глобальный inbox задач
- Авто-extract после ASR
- Дедлайны, шаринг в чат
- Extract из обычных сообщений
- Редактирование `body` пункта в v1
