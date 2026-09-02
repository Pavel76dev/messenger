# AGENTS.md

Инструкция для AI-агента в этом репозитории.

## Язык

Отвечай пользователю по-русски, если не попросили иначе.

## Источник правды

1. `specs/product.md` — продукт
2. `specs/architecture.md` — устройство кода
3. `specs/features/*.md` — фичи и критерии приёмки
4. `specs/adr/` — архитектурные решения

## Скилы

| Задача | Скил |
|--------|------|
| Новая / правка требований | `.cursor/skills/write-spec/SKILL.md` |
| Реализация фичи | `.cursor/skills/implement-from-spec/SKILL.md` |
| Проверка готовности | `.cursor/skills/review-against-spec/SKILL.md` |

## Команды

```bash
# frontend
cd frontend && npm install && npm run dev

# backend
cd backend && composer install --no-dev && php artisan serve
```

## Ограничения

- Не реализуй фичи со `status: draft`.
- Frontend: React + Redux Toolkit + MUI; не подключай UI-библиотеки без спеки.
- Backend: Laravel API + Sanctum Bearer; realtime MVP = polling.
- Минимальный дифф; без рефакторинга «заодно».
