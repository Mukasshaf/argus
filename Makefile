.PHONY: up down restart migrate seed test race e2e verify reset demo pint phpstan

up:
	docker compose up -d

down:
	docker compose down

restart: down up

migrate:
	docker compose exec -T app php artisan migrate --force

seed:
	docker compose exec -T app php artisan db:seed --force

test:
	docker compose exec -T app php artisan test

race:
	docker compose exec -T app php artisan test --testsuite=Concurrency

e2e:
	docker compose exec -T app php artisan staybridge:verify-sync

verify:
	docker compose exec -T app php artisan staybridge:verify-sync

reset:
	docker compose exec -T app php artisan migrate:fresh --seed --force

demo:
	docker compose exec -T app php artisan db:seed --force
	docker compose exec -T app php artisan staybridge:extend-inventory --days=30
	docker compose exec -T app php artisan staybridge:dispatch-sync
	docker compose exec -T app php artisan staybridge:verify-sync

pint:
	docker compose exec -T app vendor/bin/pint

phpstan:
	docker compose exec -T app vendor/bin/phpstan analyse
