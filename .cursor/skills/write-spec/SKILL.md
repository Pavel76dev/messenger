---
name: write-spec
description: >-
  Authors or updates SDLS artifacts (vibes, PDR, feature specs, tasks, ADR) using
  project templates. Use when writing requirements, drafting a feature spec, PDR,
  vibes, task breakdown, or when the user asks to document what to build before coding.
---

# Write Spec

## Перед работой

1. Прочитай `specs/sdls.md` и `specs/product.md`
2. Найди свободный `NNN` в `specs/features/`
3. Возьми шаблон из `specs/templates/`

## Что создавать

| Запрос пользователя | Артефакт |
|---------------------|----------|
| идея / ощущение продукта | `specs/vibes/` |
| решение скоупа / потоки | `specs/pdr/` |
| детальные требования | `specs/features/` |
| шаги для агента | `specs/tasks/` |
| выбор технологии | `specs/adr/` |

Для новой фичи по умолчанию создай цепочку: **PDR + Feature (`draft` или `ready`) + Tasks**.
Vibes — если меняется продуктовое ощущение; иначе сошлись на существующий vibes.

## Правила содержания

- Must = проверяемые утверждения (API, UI, данные)
- Явный **Must not** и **Вне скоупа**
- Tasks атомарные, с DoD и ожидаемыми файлами
- Не смешивай реализацию в спеку («напиши код так-то») — только контракт
- `status: draft` пока пользователь не подтвердил готовность к коду

## После записи

- Обнови ссылки между vibes/pdr/feature/tasks
- При необходимости допиши пункт в `specs/product.md` (бэклог)
- Сообщи пользователю пути файлов и спроси: перевести feature в `ready` и реализовать?

## Не делать

- Не писать production-код в этом скиле
- Не менять `implemented` фичи без явного запроса на правку требований
