---
id: 013-audio-clips
title: Tasks — аудио-фрагменты
status: done
created: 2026-10-12
feature: specs/features/013-audio-clips.md
---

# Tasks: Аудио-фрагменты

## Порядок

| # | Task | DoD | Status |
|---|------|-----|--------|
| 1 | MediaRecorder hook | start/stop, ошибки RU | done |
| 2 | Backend audio_clips API | migrate + CRUD owner | done |
| 3 | Post-record dialog | Save/Send/Cancel | done |
| 4 | MessageComposer Mic+Library | toggle + wiring | done |
| 5 | CallOverlay toggle | запись в звонке | done |
| 6 | Library UI | list/play/delete via API | done |

## Детали

### T1 — Хук записи

- **Файлы:** `resources/js/features/audioClips/useAudioRecorder.js`
- **Не делать:** UI, backend

### T2 — Сервер

- **Делает:** миграция, Model, Controller index/store/destroy, routes
- **Файлы:** `database/migrations/…_create_audio_clips_table.php`,
  `app/Models/AudioClip.php`, `app/Http/Controllers/Api/AudioClipController.php`,
  `routes/api.php`
- **Не делать:** IndexedDB

### T3 — Диалог после записи

- **Файлы:** `AudioClipActionsDialog.jsx`, API helper save

### T4 — Composer

- **Файлы:** `MessageComposer.jsx`

### T5 — CallOverlay

- **Файлы:** `CallOverlay.jsx`

### T6 — Library

- **Файлы:** `AudioClipsLibraryDialog.jsx` (+ API list/delete)
- **Не делать:** send из library

## Definition of Done

- [x] Must 013
- [x] feature → implemented
