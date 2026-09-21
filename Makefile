.PHONY: build up down sh install migrate seed test cs fix

build:
	docker compose build

up:
	docker compose up -d

down:
	docker compose down

sh:
	docker compose exec php sh

install:
	docker compose exec php composer install

migrate:
	docker compose exec php php bin/console migrate

seed:
	docker compose exec php php bin/console seed

test:
	docker compose exec php vendor/bin/phpunit

cs:
	docker compose exec php vendor/bin/php-cs-fixer fix --dry-run --diff

fix:
	docker compose exec php vendor/bin/php-cs-fixer fix
