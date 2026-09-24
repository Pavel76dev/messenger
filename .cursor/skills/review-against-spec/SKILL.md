---
name: review-against-spec
description: >-
  Reviews code against Feature Spec acceptance criteria and Tasks DoD. Use when
  checking if a feature is done, auditing gaps vs specs, or at SDLS Review stage.
---

# Review against Spec

## Вход

- Feature: `specs/features/NNN-….md`
- Tasks: `specs/tasks/NNN-….md`
- При необходимости PDR/ADR

## Как ревьюить

1. Пройди **Must** и **Критерии приёмки** — для каждого: ✅ / ❌ / ⚠️ с путём файла
2. Сверь Tasks status с реальностью в коде
3. Проверь **Must not** и Out of scope (не должны появиться)
4. Отметь расхождения спеки и кода (что устарело)

## Формат ответа

```markdown
## Вердикт: PASS | PASS WITH GAPS | FAIL

### Must / Acceptance
- ✅ …
- ❌ … — почему; где чинить

### Must not
- ✅ не нарушено / ❌ нарушено: …

### Tasks
- T1 done/pending — …

### Рекомендации
1. …
```

## Действия после review

- FAIL / gaps → не ставь `implemented`; предложи конкретные фиксы
- PASS → можно `status: implemented` и отметить DoD в tasks

Не рефакторь во время review, если пользователь не просил чинить сразу.
