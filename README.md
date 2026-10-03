Pavel
g5XU5iWt6293gQY
# Messenger (OpenServer)

Единый Laravel-проект: API + React SPA в одном домене `http://messenger`.

## Структура

```
messenger/
  app/                  # Laravel (API, модели)
  resources/js/         # React + Redux + MUI
  resources/views/      # Blade-оболочка SPA
  public/               # Document root (через корневой .htaccess)
  routes/api.php        # JSON API
  routes/web.php        # SPA fallback
  specs/                # Спеки
```

## Требования

- OpenServer с MySQL/MariaDB
- PHP 8.1+
- Node.js 18+

## База данных

В `.env` (как у соседних доменов):

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=messenger
DB_USERNAME=root
DB_PASSWORD=root
```

Создайте БД `messenger` в OpenServer (или через mysql CLI), затем:

```bash
php artisan migrate --seed
```

Демо-пользователи (пароль `password123`):
- alice@example.com
- bob@example.com

## Установка 

```bash
cd C:\OpenServer\domains\messenger
composer install
npm install
npm run build
php artisan key:generate
php artisan migrate --seed
```

## Разработка фронта

```bash
npm run dev
```

Откройте `http://messenger` (OpenServer должен быть запущен). Vite HMR — порт 5173.

## Realtime (WebSocket)

Основной сервер — `beyondcode/laravel-websockets`:

```bash
php artisan websockets:serve
# или
npm run ws
```

Альтернатива (уже установлен `@soketi/soketi`):

```bash
npm run soketi
```

В `.env` должны быть `BROADCAST_DRIVER=pusher` и `PUSHER_*` / `VITE_PUSHER_*` (см. `.env.example`).
После смены `VITE_*` пересоберите фронт.

Дашборд: `http://messenger/laravel-websockets`  
Если WS недоступен — работает polling-fallback.

> `beyondcode/laravel-websockets` 1.14 на Symfony InputBag не умеет читать массив
> `channels` (`Input value "channels" contains a non-scalar value`). Правка лежит в
> `patches/TriggerEventController.php` и копируется в `vendor/` скриптом
> `patches/apply-websockets-trigger.php` (хук `composer post-autoload-dump`).
> После применения перезапустите `php artisan websockets:serve`.

Звонки с микрофоном браузер разрешает только в безопасном контексте (`https://…`,
`http://localhost`, `http://127.0.0.1`, `http://*.localhost`). Адрес `http://messenger`
им не является: `navigator.mediaDevices` пустой и LiveKit падает на `getUserMedia`.

Для локальной проверки откройте **http://messenger.localhost** (не `http://messenger`).
Алиас OpenServer: в `OSPanel\userdata\profiles\Default_aliases.txt` строка
`messenger.localhost;messenger`, плюс в hosts — `127.0.0.1 messenger.localhost`.
После правки алиасов перезапустите OpenServer (меню → Перезапустить), иначе
панель пересоберёт `httpd.conf` без `ServerAlias` и покажет страницу
«Как вы здесь оказались?».

## Звонки (LiveKit)

Self-hosted SFU: `docker/livekit/`. Ключи в `.env` должны совпадать с `docker/livekit/livekit.yaml`.

```bash
docker compose -f docker/livekit/docker-compose.yml up -d --force-recreate
```

Проверка: `docker ps` должен показывать `0.0.0.0:7880->7880/tcp`, а не только `7880-7881/tcp`.
На Windows Hyper-V часто занимает UDP `50000–50xxx` (`netsh interface ipv4 show excludedportrange protocol=udp`) —
в compose используется диапазон `52000–52100`. Если контейнер не стартует с ошибкой `bind: … forbidden`,
смените диапазон в `docker/livekit/livekit.yaml` и `docker-compose.yml` на свободный.

Переменные (см. `.env.example`):

```
LIVEKIT_URL=ws://127.0.0.1:7880
LIVEKIT_API_KEY=APImessengerdevkey
LIVEKIT_API_SECRET=messenger_livekit_secret_change_me_32b
VITE_LIVEKIT_URL=ws://127.0.0.1:7880
```

После смены `VITE_*` пересоберите фронт (`npm run build` / `npm run dev`).

В UI: кнопки аудио/видео в шапке чата; входящий overlay; демо экрана (desktop/Android; на iOS Safari кнопка отключена).

На VPS добавьте TURN (coturn в compose закомментирован) и HTTPS — иначе мобильные клиенты за NAT/LTE не соединятся.

## API

Префикс `/api` на том же домене (Bearer Sanctum).
