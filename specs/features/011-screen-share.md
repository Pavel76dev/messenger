---
id: 011-screen-share
title: Демонстрация экрана в звонке
status: implemented
owner: team
created: 2026-09-24
---

# 011 — Демонстрация экрана

## Контекст

Расширение CallOverlay после 009/010.

## Цель

Участник звонка шарит экран; остальные видят screen track.

## User stories

- Как участник звонка на desktop/Android, я включаю демонстрацию экрана.
- Как зритель, я вижу чужой экран в основном viewport.
- Как пользователь на iOS Safari без поддержки, я вижу, что функция недоступна.

## Требования

### Must

- [x] Кнопка «Демонстрация экрана» в CallOverlay при поддержке `getDisplayMedia`
- [x] Публикация/остановка screen track через LiveKit
- [x] Подписка и отображение remote `ScreenShare` track
- [x] Если API недоступен — кнопка disabled + tooltip/hint на русском

### Must not

- [x] Не падать приложению при отказе пользователя в picker ОС

## Критерии приёмки

1. Desktop: share → второй клиент видит экран
2. Stop share → экран пропадает
3. Unsupported device: кнопка неактивна, понятный hint

## Вне скоупа

- Одновременный multi-screen layout grid advanced; запись
