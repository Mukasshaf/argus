param(
    [Parameter(Position=0)]
    [string]$Command = "help"
)

switch ($Command.ToLower()) {
    "up" {
        docker compose up -d
    }
    "down" {
        docker compose down
    }
    "restart" {
        docker compose down
        docker compose up -d
    }
    "migrate" {
        docker compose exec -T app php artisan migrate --force
        docker compose exec -T otasim php artisan migrate --force
    }
    "seed" {
        docker compose exec -T app php artisan db:seed --force
    }
    "test" {
        docker compose exec -T app php artisan test
    }
    "race" {
        docker compose exec -T app php artisan test --testsuite=Concurrency
    }
    "verify" {
        docker compose exec -T app php artisan staybridge:verify-sync
    }
    "reset" {
        docker compose exec -T app php artisan migrate:fresh --seed --force
    }
    "demo" {
        docker compose exec -T app php artisan db:seed --force
        docker compose exec -T app php artisan staybridge:extend-inventory --days=30
        docker compose exec -T app php artisan staybridge:dispatch-sync
        docker compose exec -T app php artisan staybridge:verify-sync
    }
    default {
        Write-Host "StayBridge PowerShell Runner" -ForegroundColor Cyan
        Write-Host "----------------------------"
        Write-Host "Usage: .\run.ps1 [command]"
        Write-Host ""
        Write-Host "Commands:"
        Write-Host "  up       Start all containers in background"
        Write-Host "  down     Stop all containers"
        Write-Host "  restart  Restart containers"
        Write-Host "  migrate  Run database migrations for core and otasim"
        Write-Host "  seed     Seed Lotus Residency initial data"
        Write-Host "  test     Run test suite"
        Write-Host "  race     Run concurrency race tests"
        Write-Host "  verify   Run sync verification"
        Write-Host "  reset    Wipe and reset database fresh"
        Write-Host "  demo     Run full demo scenario"
        Write-Host ""
    }
}
