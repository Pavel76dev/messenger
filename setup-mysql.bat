@echo off
REM Run after OpenServer MySQL is started (root/root)
cd /d %~dp0

echo Creating database messenger...
"C:\OpenServer\modules\database\MySQL-8.0-Win10\bin\mysql.exe" -uroot -proot -e "CREATE DATABASE IF NOT EXISTS messenger CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
if errorlevel 1 (
  "C:\OpenServer\modules\database\MySQL-5.7-Win10\bin\mysql.exe" -uroot -proot -e "CREATE DATABASE IF NOT EXISTS messenger CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
)

echo Migrating and seeding...
php artisan migrate --seed --force
echo Done.
pause
