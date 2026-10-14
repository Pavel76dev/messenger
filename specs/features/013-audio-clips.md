---
id: 013-audio-clips
title: Аудио-фрагменты (запись, сохранение, отправка)
status: implemented
owner: team
created: 2026-10-12
---

# 013 — Аудио-фрагменты

## Контекст

PDR: `specs/pdr/013-audio-clips.md`. ADR: `specs/adr/005-audio-clips-local-storage.md`.

## Цель

Запись своего микрофона toggle-кнопкой; сохранить на сервер или отправить в чат;
библиотека с play/delete.

## User stories

- Как пользователь, я включаю/выключаю запись кнопкой рядом со скрепкой и выбираю
  сохранить или отправить.
- Как участник ВКС, я делаю то же из панели звонка (кнопка западает).
- Как пользователь, я открываю библиотеку, слушаю и удаляю клипы.

## Требования

### Must

- [x] Composer: Mic + Library рядом с AttachFile
- [x] Toggle запись (pressed/`aria-pressed`); одинаково вне звонка и в CallOverlay
- [x] После stop с blob — диалог Сохранить / Отправить / Отмена
- [x] Отправить → `POST .../messages` с `files[]` audio (006)
- [x] Сохранить → `POST /api/audio-clips` (multipart); список `GET /api/audio-clips`
- [x] Удалить → `DELETE /api/audio-clips/{id}` (только свои)
- [x] Ответ клипа: `{ id, url, mime, size, duration_ms, created_at }`
- [x] Library: дата-время, play, delete (без send из library)
- [x] Только local mic; remote не микшировать
- [x] Ошибки mic/API на русском
- [x] Файл ≤ 10 MB

### Should

- [x] Длительность во время записи (мм:сс)
- [x] Авто-стоп ~5 мин
- [x] Disabled Mic без открытого диалога / при send loading

### Must not

- [ ] Remote audio / полная запись конференции
- [ ] IndexedDB как основное хранилище
- [ ] Send из library (v1)
- [ ] Ломать LiveKit mute и обычные сообщения

## UX / UI

- Composer: `[Attach] [Mic] [Library] [Text] [Send]`
- CallOverlay: toggle Mic-record рядом с controls
- Library: `ДД.ММ.ГГГГ ЧЧ:ММ`, Play, Delete

## Технические заметки

- Таблица `audio_clips`; диск `public` → `audio_clips/{userId}/…`
- Хук MediaRecorder; reuse `sendMessage`
- Secure context (`mediaAccess.js`)

## Критерии приёмки

1. Toggle → Отправить → аудио в ленте
2. Сохранить → в library с датой; Play / Delete
3. Во время call: toggle в overlay; только свой звук
4. Отказ mic → ошибка, SPA жив
5. Чужой клип: DELETE → 403/404

## Вне скоупа

- Remote mix, sync UI beyond server list, send из library, hold, транскрипция
