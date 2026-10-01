# Architecture (OpenServer)

## Обзор

Один домен OpenServer (`http://messenger`): Laravel JSON API + React SPA.

```
browser → http://messenger → public/ (через корневой .htaccess)
  ├─ /api/*  → Laravel API (Sanctum Bearer)
  └─ /*      → Blade app.blade.php → Vite/React
```

## Каталоги

| Путь | Назначение |
|------|------------|
| `app/` | Laravel: модели, API-контроллеры |
| `resources/js/` | React + Redux Toolkit + MUI |
| `resources/views/app.blade.php` | HTML-оболочка SPA |
| `routes/api.php` | REST API |
| `routes/web.php` | SPA fallback |
| `public/` | Document root |
| `specs/` | Требования |

## База данных

MySQL OpenServer (как соседние домены):

- host `127.0.0.1:3306`
- database `messenger`
- user/password `root` / `root`

## Frontend

- Сборка: `npm run build` → `public/build`
- Dev: `npm run dev` (Vite) + OpenServer для PHP
- API base: same-origin (пустой `VITE_API_URL`)

## Файлы

- Диск Laravel `public` → `storage/app/public`, symlink `public/storage`
- Аватары: `avatars/{userId}.{ext}` → URL `/storage/...`
- Вложения сообщений: `attachments/{conversationId}/{uuid}_{name}` → URL `/storage/...`
- В JSON API отдаём `avatar_url` / `attachments[].url` (публичные пути)

## Звонки (LiveKit)

- Сигналинг звонка: Laravel REST + Echo (ADR-002)
- Медиа: self-hosted LiveKit SFU (ADR-003), Docker: `docker/livekit/`
- Клиент получает JWT: `POST /api/calls/{id}/token`
- Env: `LIVEKIT_URL`, `LIVEKIT_API_KEY`, `LIVEKIT_API_SECRET`, `VITE_LIVEKIT_URL`

## Правила

1. Фича → SDLS (vibes/PDR/spec/tasks) → код.
2. Не смешивать бизнес-логику в React: только REST (+ Echo для realtime).
3. Realtime: WebSocket; polling — fallback (ADR-002).
4. Медиа звонков — только через LiveKit, не через Echo/PHP.
5. Минимальный дифф; спека — источник правды.
