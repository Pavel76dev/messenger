---
id: 017-assistant-workspace-context
title: Tasks — Контекст ассистента (чаты, документы, аудио)
status: done
created: 2026-10-21
feature: specs/features/017-assistant-workspace-context.md
---

# Tasks: Контекст ассистента

Атомарные шаги для агента. Один task = один проверяемый результат.
Не начинать task, пока предыдущий с `[blocks]` не выполнен (если указан).

## Порядок

| # | Task | DoD (готово когда) | Status |
|---|------|--------------------|--------|
| 1 | Миграция extract на вложениях + config капов/роутера | migrate ok; config ключи есть | done |
| 2 | Авто-ASR при `POST /api/audio-clips` | save → pending + фон Job 014 | done |
| 3 | Сервис извлечения текста из вложений | txt/pdf/docx → кэш done/error | done |
| 4 | `AssistantWorkspaceContext` (чаты + вложения + клипы) | только свои данные; капы; без wait ASR | done |
| 5 | Роутер + обогащение `GenerateAiReply` | false → без workspace; true → с пакетом | done |
| 6 | UI library (опц.): Save без обязательного Transcribe | UX согласован с auto-ASR | done |
| 7 | Приёмка по критериям Feature | 1–7 ✓ | done |
| 8 | Статусы спек → implemented после review | feature/tasks/adr | done |

## Детали

### T1 — Миграция и config

- **Делает:** поля extract на `message_attachments`; config капов контекста + prompt/флаги роутера.
- **Файлы (ожидаемые):** migration, `config/llm.php` (или `assistant_context`), `.env.example`
- **Не делать:** UI; вызов LLM
- **Проверка:** migrate; config читается
- **Blocks:** —

### T2 — Авто-транскрибация при save

- **Делает:** после создания `AudioClip` выставить pending и dispatch существующий ASR-пайплайн (afterResponse / queue), не блокируя HTTP.
- **Файлы (ожидаемые):** контроллер/сервис создания клипа; reuse Job/command 014
- **Не делать:** новый ASR HTTP; ожидание инференса в request
- **Проверка:** save клипа → pending → done/error без ручного Transcribe
- **Blocks:** —

### T3 — Document text extraction

- **Делает:** extract по mime из Feature; кэш на attachment; неизвестный mime — контролируемый skip/error.
- **Файлы (ожидаемые):** `app/Services/AttachmentTextExtractor.php` (+ deps по ADR)
- **Не делать:** OCR; отправку байтов в LLM
- **Проверка:** txt + pdf или docx → done; бинарь → error без exception наружу
- **Blocks:** T1

### T4 — Сборка workspace-контекста

- **Делает:** пакет по `user_id`: диалоги (участник), сообщения (без AI-чата), extract/мета вложений, клипы с транскриптами/`done` + пометки для не-done; капы; каталог источников.
- **Файлы (ожидаемые):** `app/Services/AssistantWorkspaceContext.php`
- **Не делать:** wait ASR; HTTP API для фронта (не обязателен); чужие данные
- **Проверка:** ACL; усечение при капе
- **Blocks:** T1, T3

### T5 — Роутер + GenerateAiReply

- **Делает:** (1) router LLM → JSON `need_workspace`; parse fail → true; (2) если true — собрать workspace и reply с контекстом; если false — reply как 012; обновить system prompts; afterResponse.
- **Файлы (ожидаемые):** `app/Jobs/GenerateAiReply.php`, config prompts, вызов T4
- **Не делать:** tools API; всегда полный дамп; триггер вне чата с ботом
- **Проверка:** «привет» без большого context; запрос про другой чат — context в reply-вызове
- **Blocks:** T4

### T6 — UI library под auto-ASR

- **Делает:** минимальный UX: после Save видно pending без обязательного клика Transcribe; кнопка Transcribe для retry/error.
- **Файлы (ожидаемые):** компоненты library в `resources/js/...`
- **Не делать:** новый экран источников; новые UI-библиотеки
- **Проверка:** ручной сценарий save → pending в UI
- **Blocks:** T2

### T7 — Приёмка

- **Делает:** критерии Feature 1–7.
- **Файлы (ожидаемые):** опц. smoke-скрипт
- **Не делать:** рефакторинг вне скоупа
- **Проверка:** чеклист
- **Blocks:** T5, T6

### T8 — Закрытие SDLS

- **Делает:** feature → `implemented`, tasks done, ADR → `accepted`, `product.md`.
- **Файлы (ожидаемые):** спеки
- **Не делать:** код
- **Проверка:** статусы
- **Blocks:** T7 + review-against-spec

## Definition of Done (фича целиком)

- [x] Все Must из Feature Spec
- [x] Критерии приёмки выполнены (smoke extract + код по tasks; e2e с api-llm/ASR — вручную)
- [x] `status` фичи → `implemented`
- [ ] Review-against-spec без критичных gaps
