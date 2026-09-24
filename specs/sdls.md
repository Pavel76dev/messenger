# SDLS — Spec-Driven Development Lifecycle (для AI)

Метод ведения фич в этом репозитории: сначала артефакты правды, потом код.
Агент **не пишет код**, пока нет спеки со `status: ready` (или явного исключения от пользователя).

## Зачем

- Меньше «додумывания» агентом
- Один контракт для backend / frontend / review
- Можно продолжить работу в другом чате без потери контекста
- Легко проверять готовность по чеклисту

## Стадии

```
Vibes → PDR → Feature Spec → ADR (если нужно) → Tasks → Implement → Review
```

| Стадия | Папка / файл | Вопрос, на который отвечает |
|--------|--------------|-----------------------------|
| **Vibes** | `specs/vibes/` | Какой продукт и ощущение UX? Какие жёсткие рамки? |
| **PDR** | `specs/pdr/` | Что решаем, что в скоупе/вне, ключевые потоки? |
| **Feature Spec** | `specs/features/` | Must / Should / Must not + критерии приёмки |
| **ADR** | `specs/adr/` | Почему выбрали конкретную архитектуру? |
| **Tasks** | `specs/tasks/` | Атомарные шаги для агента (порядок, DoD) |
| **Implement** | код | Реализация строго по Tasks + Feature Spec |
| **Review** | — | Код ↔ спека; чеклист приёмки |

## Правила для AI

1. Источник правды — `specs/`, не «память чата».
2. Не реализовывать `status: draft`.
3. Минимальный дифф; без рефакторинга «заодно».
4. Новая фича = полный проход SDLS (хотя бы vibes→pdr→feature→tasks).
5. Hotfix без спеки — только если пользователь явно сказал «без спеки»; после фикса дописать спеку.
6. После реализации: обновить статусы (`ready` → `implemented`) и отметить tasks.

## Скилы

| Стадия | Скил |
|--------|------|
| Весь цикл / старт фичи | `.cursor/skills/sdls-flow/SKILL.md` |
| Vibes / PDR / Feature / Tasks | `.cursor/skills/write-spec/SKILL.md` |
| Код по tasks | `.cursor/skills/implement-from-spec/SKILL.md` |
| Сверка | `.cursor/skills/review-against-spec/SKILL.md` |

## Пример (сделано в проекте)

Реакции + realtime WebSocket:

1. [`vibes/001-messenger.md`](./vibes/001-messenger.md)
2. [`pdr/007-message-reactions.md`](./pdr/007-message-reactions.md)
3. [`features/007-message-reactions.md`](./features/007-message-reactions.md)
4. [`adr/002-websockets.md`](./adr/002-websockets.md)
5. [`tasks/007-message-reactions.md`](./tasks/007-message-reactions.md)

## Именование

- id фичи: `NNN-kebab-name` (трёхзначный номер)
- vibes продукта: `001-messenger.md` (или тематические `00N-…`)
- PDR / tasks / feature с **одним и тем же** `NNN`
