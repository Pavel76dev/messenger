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

## Правила

1. Фича → спека → код.
2. Не смешивать бизнес-логику в React: только REST.
3. Realtime MVP: polling; WebSocket — отдельная фича.
