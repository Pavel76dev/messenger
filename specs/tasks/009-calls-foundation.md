---
id: 009-calls-foundation
title: Tasks — основа звонков 1:1
status: done
created: 2026-09-24
feature: specs/features/009-calls-foundation.md
---

# Tasks: Основа звонков 1:1

## Порядок

| # | Task | DoD | Status |
|---|------|-----|--------|
| 1 | Docker LiveKit + env/config | compose + .env.example + config/livekit.php | done |
| 2 | Миграция + модель Call | таблица, Eloquent, связи | done |
| 3 | Token service + CallController API | REST + JWT | done |
| 4 | Events + SafeBroadcast | 4 события на user channels | done |
| 5 | Frontend calls slice + overlay | Redux + CallOverlay + кнопки | done |
| 6 | Echo listeners + docs | user channel call.* ; README | done |

## Definition of Done (фича целиком)

- [x] Все Must из Feature Spec
- [x] Критерии приёмки выполнены
- [x] `status` фичи → `implemented`
