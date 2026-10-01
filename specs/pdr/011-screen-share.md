---
id: 011-screen-share
title: PDR — демонстрация экрана
status: accepted
created: 2026-09-24
related_vibes: specs/vibes/001-messenger.md
related_feature: specs/features/011-screen-share.md
---

# PDR: Демонстрация экрана

## Проблема

В звонке нельзя показать экран — неудобно для совместной работы / демо.

## Решение

Публикация screen track через LiveKit (`setScreenShareEnabled`); кнопка в CallOverlay;
на устройствах без `getDisplayMedia` (типичный iOS Safari) — disabled + подсказка.

## Скоуп

### In

- Toggle screen share в активном звонке (1:1 и group)
- Отображение remote screen track (приоритет над camera в layout)
- Capability gating на mobile/iOS

### Out

- Аннотации, remote control, запись экрана

## Готовность к Feature Spec

- [x] Скоуп согласован
- [x] Out of scope ясен
- [x] Нет блокирующих открытых вопросов

## Связи

- Depends: 009, 010
- Feature: `specs/features/011-screen-share.md`
- Tasks: `specs/tasks/011-screen-share.md`
