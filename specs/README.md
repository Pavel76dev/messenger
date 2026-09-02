# Specs

Источник правды по продукту и фичам. Код следует спекам.

## Порядок чтения

1. [`product.md`](./product.md)
2. [`architecture.md`](./architecture.md)
3. [`features/`](./features/)
4. [`adr/`](./adr/) — принятые архитектурные решения

## Workflow

| Шаг | Действие | Скил |
|-----|----------|------|
| 1 | Написать/обновить спеку | `write-spec` |
| 2 | Реализовать по спеке | `implement-from-spec` |
| 3 | Сверить код со спекой | `review-against-spec` |

## Статусы фич

- `draft` — не реализовывать
- `ready` — можно в работу
- `implemented` — код соответствует спеке
- `deprecated` — не использовать

## Шаблоны

- [`templates/feature.md`](./templates/feature.md)
- [`templates/adr.md`](./templates/adr.md)
