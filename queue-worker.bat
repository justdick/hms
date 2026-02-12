@echo off
cd /d "%~dp0"
echo Queue worker starting...
php artisan queue:work --sleep=3 --tries=3
