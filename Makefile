DC      = docker compose
# в CI нет терминала: -T отключает выделение TTY
EXEC    = $(DC) exec $(if $(CI),-T,) -u www-data app
CONSOLE = $(EXEC) php bin/console

.PHONY: init up down build ps logs sh composer console migrate migrations-diff worker-logs worker-restart import front-install front-start front-build front-test fixtures test test-unit e2e phpstan cs cs-fix quality user

init: ## первый запуск: .env, сборка, зависимости, миграции
	@test -f .env || cp .env.example .env
	$(DC) up -d --build
	$(EXEC) composer install
	$(CONSOLE) migrations:migrate --no-interaction --allow-no-migration
	$(CONSOLE) app:user:create
	$(DC) restart worker

up: ## запустить контейнеры
	$(DC) up -d

down: ## остановить контейнеры
	$(DC) down

build: ## пересобрать образы
	$(DC) build

ps: ## статус контейнеров
	$(DC) ps

logs: ## логи всех сервисов
	$(DC) logs -f --tail=100

sh: ## shell в контейнере app
	$(EXEC) sh

composer: ## make composer cmd="require vendor/package"
	$(EXEC) composer $(cmd)

console: ## make console cmd="orm:validate-schema"
	$(CONSOLE) $(cmd)

migrations-diff: ## сгенерировать миграцию по изменениям в сущностях
	$(CONSOLE) migrations:diff --no-interaction

migrate: ## применить миграции
	$(CONSOLE) migrations:migrate --no-interaction --allow-no-migration

worker-logs: ## логи воркера очереди
	$(DC) logs -f --tail=100 worker

worker-restart: ## перезапустить воркер (нужно после изменений PHP-кода)
	$(DC) restart worker

user: ## создать пользователя из ADMIN_USERNAME/ADMIN_PASSWORD (.env) или: make user args="login password"
	$(CONSOLE) app:user:create $(args)

import: ## тестовый импорт файла-примера (вход под ADMIN_USERNAME из .env)
	@set -a; . ./.env; set +a; \
	TOKEN=$$(curl -s -H 'Content-Type: application/json' \
		-d "{\"username\":\"$$ADMIN_USERNAME\",\"password\":\"$$ADMIN_PASSWORD\"}" \
		http://localhost:$${APP_PORT:-8080}/api/auth/login | sed -n 's/.*"token" *: *"\([^"]*\)".*/\1/p'); \
	curl -s -H "Authorization: Bearer $$TOKEN" -F "file=@backend/tests/fixtures/import_example.ods" \
		http://localhost:$${APP_PORT:-8080}/api/import; echo

front-install: ## установить зависимости фронтенда (нужен Node.js 22 в WSL)
	cd frontend && npm ci

front-start: ## dev-сервер Angular на http://localhost:4200 (API проксируется на :8080)
	cd frontend && npm start

front-build: ## пересобрать и перезапустить фронтенд в Docker (http://localhost:8081)
	$(DC) up -d --build frontend

# Node.js в контейнере (Debian, не Alpine: на musl нет нативного sass-embedded и esbuild-бинарников под glibc)
NODE = docker run --rm -u $$(id -u):$$(id -g) -e HOME=/tmp -e NG_CLI_ANALYTICS=false -v $(CURDIR)/frontend:/app -w /app node:22-bookworm-slim

front-test: ## юнит-тесты фронтенда (в Docker, Node.js в WSL не нужен; зависимости ставятся один раз)
	$(NODE) sh -c "[ -d node_modules/.bin ] || npm ci --no-audit --no-fund; npm test"

fixtures: ## загрузить тестовые данные (30 товаров с атрибутами и картинками; очищает БД!)
	$(CONSOLE) app:fixtures:load

test: ## все тесты бэкенда: unit, интеграционные, API (отдельная БД products_test)
	$(EXEC) vendor/bin/phpunit

test-unit: ## только unit-тесты бэкенда (без БД)
	$(EXEC) vendor/bin/phpunit --testsuite Unit

e2e: ## e2e-тесты Playwright в Docker против запущенного приложения
	$(DC) --profile e2e run --rm $(if $(CI),-T,) e2e

phpstan: ## статический анализ PHPStan (level 6)
	$(EXEC) vendor/bin/phpstan analyse --memory-limit=1G

cs: ## проверка стиля кода (PHP CS Fixer, без изменений)
	$(EXEC) vendor/bin/php-cs-fixer fix --dry-run --diff

cs-fix: ## автоматически исправить стиль кода
	$(EXEC) vendor/bin/php-cs-fixer fix

quality: phpstan cs test ## всё вместе: анализ, стиль, тесты
