---
name: sdls-flow
description: >-
  Runs the project Spec-Driven Development Lifecycle (Vibes → PDR → Feature Spec →
  ADR → Tasks → Implement → Review) for AI-assisted feature work. Use when starting
  a new feature, when the user mentions SDLS, vibes, PDR, tasks, or asks how to
  structure work for the agent.
---

# SDLS Flow

Прочитай `specs/sdls.md`, затем веди фичу по стадиям. Не прыгай к коду без артефактов.

## Когда применять

- «новая фича», «сделай по SDLS», «vibes / PDR / tasks»
- Пользователь дал идею без спеки — сначала оформить артефакты

## Стадии (чеклист)

Скопируй и отмечай:

```
SDLS Progress:
- [ ] 1. Vibes   → specs/vibes/
- [ ] 2. PDR     → specs/pdr/
- [ ] 3. Feature → specs/features/ (status: ready)
- [ ] 4. ADR     → specs/adr/ (только если есть выбор архитектуры)
- [ ] 5. Tasks   → specs/tasks/
- [ ] 6. Implement (skill: implement-from-spec)
- [ ] 7. Review    (skill: review-against-spec)
```

## Правила перехода

| С | На | Условие |
|---|----|---------|
| Vibes | PDR | Вайб и рамки записаны |
| PDR | Feature | In/Out согласованы, нет блокеров |
| Feature | Tasks | `status: ready`, Must сформулированы |
| Tasks | Implement | Tasks с DoD; пользователь не запретил код |
| Implement | Review | Код по tasks готов |
| Review | Done | Критерии приёмки ✓; feature → `implemented` |

## Нумерация

Один `NNN` на PDR + Feature + Tasks. Vibes продукта может быть общим (`001-messenger`).

## Шаблоны

- `specs/templates/vibes.md`
- `specs/templates/pdr.md`
- `specs/templates/feature.md`
- `specs/templates/tasks.md`
- `specs/templates/adr.md`

## Пример в репо

`007-message-reactions` — полный проход: vibes → pdr → feature → adr/002 → tasks.

## Язык

Артефакты и ответы пользователю — на русском (если не попросили иначе).
