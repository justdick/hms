@echo off
cd /d "%~dp0"
echo ========================================
echo  Repairing sessions table...
echo ========================================
echo.
php artisan tinker --execute="$result = DB::select('REPAIR TABLE sessions'); print_r($result);"
echo.
echo ========================================
echo  Done!
echo ========================================
echo.
pause
