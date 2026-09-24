---
name: implement-from-spec
description: >-
  Implements a feature from SDLS Feature Spec and Tasks with minimal diff. Use when
  the user asks to implement a ready feature, code from spec/tasks, or continue
  SDLS at the Implement stage.
---

# Implement from Spec

## Входные условия

1. Feature Spec: `status: ready` (или пользователь явно велел кодить draft)
2. Есть `specs/tasks/NNN-….md` — работай **по порядку tasks**
3. Прочитай связанный PDR и ADR (если есть)

Если спеки нет — сначала `write-spec` / `sdls-flow`, не изобретай требования.

## Процесс

```
Implement Progress:
- [ ] Прочитал feature + tasks + ADR
- [ ] Выполнил tasks по порядку
- [ ] Проверил DoD каждого task
- [ ] Сборка / migrate без ошибок (по возможности)
- [ ] Обновил статусы tasks; feature → implemented (после приёмки)
```

## Ограничения репозитория

- Frontend: React + Redux Toolkit + MUI; без новых UI-библиотек без спеки
- Backend: Laravel API + Sanctum Bearer
- Realtime: WebSocket + polling fallback (см. ADR-002); не ломай fallback
- Минимальный дифф; без рефакторинга «заодно»
- Язык UI/ответов: русский

## Definition of Done task

- Код соответствует Must спеки для этого task
- Нет секретов в коммите
- Не оставляй мёртвый CDN/иконки, если спека запрещает

## После реализации

Предложи `review-against-spec`. Не коммить, пока пользователь не попросил.
