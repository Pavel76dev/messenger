# Specs

Источник правды по продукту и фичам. Код следует спекам.
Метод работы агента: **[SDLS](./sdls.md)** (Vibes → PDR → Feature → ADR → Tasks → Implement → Review).

## Порядок чтения

1. [`sdls.md`](./sdls.md) — жизненный цикл
2. [`product.md`](./product.md)
3. [`vibes/`](./vibes/)
4. [`architecture.md`](./architecture.md)
5. [`pdr/`](./pdr/) → [`features/`](./features/) → [`tasks/`](./tasks/)
6. [`adr/`](./adr/)

## Workflow

| Шаг | Действие | Скил |
|-----|----------|------|
| 0 | Вести цикл SDLS | `sdls-flow` |
| 1 | Vibes / PDR / Feature / Tasks | `write-spec` |
| 2 | Реализовать по tasks | `implement-from-spec` |
| 3 | Сверить код со спекой | `review-against-spec` |

## Статусы фич

- `draft` — не реализовывать
- `ready` — можно в работу
- `implemented` — код соответствует спеке
- `deprecated` — не использовать

## Шаблоны

- [`templates/vibes.md`](./templates/vibes.md)
- [`templates/pdr.md`](./templates/pdr.md)
- [`templates/feature.md`](./templates/feature.md)
- [`templates/tasks.md`](./templates/tasks.md)
- [`templates/adr.md`](./templates/adr.md)

## Пример полного прохода

`007-message-reactions` (реакции + WebSocket): vibes `001` → pdr/feature/tasks `007` → adr `002`.
