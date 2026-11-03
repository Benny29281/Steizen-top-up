@echo off
setlocal
pushd "%~dp0"
if exist ".tools\php\php.exe" (
    ".tools\php\php.exe" artisan serve --host=localhost --port=8000
) else (
    php artisan serve --host=localhost --port=8000
)
popd
endlocal
