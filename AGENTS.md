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
```

## Ограничения

- Не реализуй фичи со `status: draft`.
- Frontend: React + Redux Toolkit + MUI; не подключай UI-библиотеки без спеки.
- Backend: Laravel API + Sanctum Bearer.
- Realtime: WebSocket + polling fallback (ADR-002).
- Реакции: нативные эмодзи браузера (не CDN-иконки).
- Минимальный дифф; без рефакторинга «заодно».
