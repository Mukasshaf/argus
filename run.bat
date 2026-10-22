@echo off
setlocal

set CMD=%1

if "%CMD%"=="" goto help
if "%CMD%"=="up" goto up
if "%CMD%"=="down" goto down
if "%CMD%"=="restart" goto restart
if "%CMD%"=="migrate" goto migrate
if "%CMD%"=="seed" goto seed
if "%CMD%"=="test" goto test
if "%CMD%"=="race" goto race
if "%CMD%"=="verify" goto verify
if "%CMD%"=="reset" goto reset
if "%CMD%"=="demo" goto demo
goto help

:up
docker compose up -d
goto end

:down
docker compose down
goto end

:restart
docker compose down
docker compose up -d
goto end

:migrate
docker compose exec -T app php artisan migrate --force
docker compose exec -T otasim php artisan migrate --force
goto end

:seed
docker compose exec -T app php artisan db:seed --force
goto end

:test
docker compose exec -T app php artisan test
goto end

:race
docker compose exec -T app php artisan test --testsuite=Concurrency
goto end

:verify
docker compose exec -T app php artisan staybridge:verify-sync
goto end

:reset
docker compose exec -T app php artisan migrate:fresh --seed --force
goto end

:demo
docker compose exec -T app php artisan db:seed --force
docker compose exec -T app php artisan staybridge:extend-inventory --days=30
docker compose exec -T app php artisan staybridge:dispatch-sync
docker compose exec -T app php artisan staybridge:verify-sync
goto end

:help
echo StayBridge Command Runner
echo -------------------------
echo Usage: run [command]
echo.
echo Commands:
echo   up       Start all containers in background
echo   down     Stop all containers
echo   restart  Restart containers
echo   migrate  Run database migrations for core and otasim
echo   seed     Seed Lotus Residency initial data
echo   test     Run test suite
echo   race     Run concurrency race tests
echo   verify   Run sync verification
echo   reset    Wipe and reset database fresh
echo   demo     Run full demo scenario
echo.
goto end

:end
endlocal
