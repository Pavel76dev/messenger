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

## API

Префикс `/api` на том же домене (Bearer Sanctum).
