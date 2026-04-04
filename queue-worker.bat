@echo off
cd /d "C:\wamp64\www\hms"
echo Queue worker starting...
C:\wamp64\bin\php\php8.4.15\php.exe artisan queue:work --sleep=3 --tries=3
