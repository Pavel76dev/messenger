---
id: 015-transcript-task-lists
title: Tasks — Список задач из транскрипта
status: done
created: 2026-10-17
feature: specs/features/015-transcript-task-lists.md
---

# Tasks: Список задач из транскрипта

Атомарные шаги для агента. Один task = один проверяемый результат.

## Порядок

| # | Task | DoD (готово когда) | Status |
|---|------|--------------------|--------|
| 1 | Миграции + модели | таблицы/модели; FK clip обязателен | done |
| 2 | LLM extract + фон | JSON→список у клипа или empty/error | done |
| 3 | API extract + lists у клипа | endpoints + clip payload | done |
| 4 | UI у строки клипа в library | extract, пункты, toggle, delete | done |
| 5 | Review / статусы | feature → implemented | done |

## Детали

### T1 — Миграции и модели

- **Делает:** `task_lists` (`audio_clip_id` NOT NULL), `task_items`; поля `tasks_extract_*` на `audio_clips` для poll. Eloquent + сериализация для API клипа.
- **Файлы (ожидаемые):** migrations, `TaskList`, `TaskItem`, правки `AudioClip`
- **Не делать:** UI, LLM, глобальный index всех списков пользователя как продукт
- **Проверка:** migrate; список привязан к clip id
- **[blocks]** —

### T2 — Сервис LLM + фон `[blocks: T1]`

- **Делает:** prompt; `LlmClient`; парсинг JSON; create list/items на клипе или empty/error; HTTP не блокировать.
- **Файлы (ожидаемые):** `app/Services/…`, Command/Job + background spawn при необходимости
- **Не делать:** облако в обход `LLM_API_URL`
- **Проверка:** фикстурный текст → rows с `audio_clip_id`; пустой JSON → empty без rows

### T3 — API `[blocks: T2]`

- **Делает:**  
  - `POST /api/audio-clips/{id}/extract-tasks` → 202 + pending на клипе  
  - `GET /api/audio-clips` включает связанные `task_lists`(+items) **или** `GET /api/audio-clips/{id}/task-lists`  
  - `PATCH /api/task-items/{id}` `{status}`  
  - `DELETE /api/task-lists/{id}`
- **Файлы (ожидаемые):** controllers, `routes/api.php`
- **Не делать:** обязательный `GET /api/task-lists` как главный UI-контракт
- **Проверка:** 403; clip JSON содержит списки после extract

### T4 — Frontend library `[blocks: T3]`

- **Делает:** кнопка extract у клипа/`done`; pending/empty/error; блок пунктов **под этим клипом**; checkbox; удаление списка. Без отдельного «Мои задачи».
- **Файлы (ожидаемые):** `AudioClipsLibraryDialog` (+ мелкие компоненты в `features/audioClips`)
- **Не делать:** кнопка в composer для глобального inbox
- **Проверка:** критерии приёмки 1–2 и 5 feature

### T5 — Review `[blocks: T4]`

- **Делает:** Must/приёмка; statuses `implemented`; `product.md`
- **Проверка:** `review-against-spec`

## Definition of Done (фича целиком)

- [x] Все Must из Feature Spec
- [x] Критерии приёмки выполнены
- [x] `status` фичи → `implemented`
- [x] Review-against-spec без критичных gaps
