# ---------------------------------------------------------------------------
# PromERP — часто используемые команды разработки
#
#   make help        — список команд
#   make restore     — развернуть среду с нуля (PHP + PostgreSQL + конфиги + миграции)
#   make setup       — подготовить конфиги и каталоги
#   make up          — поднять docker compose
#   make migrate     — накатить миграции БД
#   make seed        — создать пользователя-администратора
#   make test        — phpunit
#   make lint        — проверка синтаксиса PHP
#   make doctor      — диагностика окружения
#   make new-module id=401 slug=tasks name="Задачи"
#   make context module=erp401_wms     — контекст одного модуля (для агентов)
#   make check-module module=erp401_wms — проверки только этого модуля
# ---------------------------------------------------------------------------

-include .env
export

MODULE   ?= $(module)
MODULE_ID = $(shell echo "$(MODULE)" | grep -oE '[0-9]{3}' | head -1)

DOCKER   ?= docker compose
CONSOLE  ?= bin/console
PHPUNIT  ?= bin/test

.DEFAULT_GOAL := help
.PHONY: help restore restore-pg restore-php restore-app setup up down restart logs shell ps \
        migrate migrate-status migrate-down migrate-dry seed test test-coverage lint audit smoke framework \
        check doctor modules new-module fresh cache-clear console context check-module docs docs-check

help: ## показать эту справку
	@grep -hE '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) \
		| awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-18s\033[0m %s\n", $$1, $$2}'

# --- Окружение --------------------------------------------------------------

restore: ## развернуть среду с нуля: PostgreSQL + PHP + конфиги + миграции
	bash bin/restore-env.sh $(if $(filter serve,$(MAKECMDGOALS)),--serve,)

restore-pg: ## только PostgreSQL (переносимые бинари из PyPI pgserver)
	bash bin/restore-env.sh --pg-only

restore-php: ## только PHP (apt или сборка из исходников)
	bash bin/restore-env.sh --php-only

restore-app: ## только конфиги, .env, миграции и админ (PHP и БД уже есть)
	bash bin/restore-env.sh --app-only

serve: ## поднять dev-сервер (в паре с restore: make restore serve)
	PORT=$(PORT) bash bin/serve

setup: ## создать конфиги, каталоги и .env
	bash bin/setup.sh

up: ## поднять стек (nginx, php-fpm, postgres, adminer, mailpit)
	$(DOCKER) up -d

down: ## остановить стек
	$(DOCKER) down

restart: down up ## перезапустить стек

logs: ## логи всех сервисов
	$(DOCKER) logs -f

ps: ## состояние сервисов
	$(DOCKER) ps

shell: ## shell внутри php-контейнера
	$(DOCKER) exec php bash

console: ## выполнить CLI-задачу: make console task=module:list
	$(CONSOLE) $(task)

cache-clear: ## очистить кэш Kohana
	rm -rf application/cache/*

# --- База данных ------------------------------------------------------------

migrate: ## накатить миграции
	$(CONSOLE) db:migrate

migrate-status: ## состояние миграций
	$(CONSOLE) db:migrate --status

migrate-dry: ## что будет накатано (без изменений)
	$(CONSOLE) db:migrate --dry-run

migrate-down: ## откатить миграцию: make migrate-down class=pgMigration_001_...
	$(CONSOLE) db:migrate --down=$(class)

seed: ## создать администратора (параметры из .env)
	$(CONSOLE) auth:user --email=$(or $(ADMIN_EMAIL),admin@promerp.local) \
		--password=$(or $(ADMIN_PASSWORD),admin12345) \
		--username=$(or $(ADMIN_USERNAME),admin) --admin

fresh: ## ПЕРЕСОЗДАТЬ схему БД с нуля (drop schema public + миграции)
	@echo "Внимание: все данные будут удалены. Прервите (Ctrl+C) в течение 5 секунд..."; sleep 5
	$(CONSOLE) db:migrate --status > /dev/null
	$(DOCKER) exec -T postgres psql -U $(DB_USERNAME) -d $(DB_DATABASE) -c "DROP SCHEMA public CASCADE; CREATE SCHEMA public;"
	$(CONSOLE) db:migrate
	$(MAKE) seed

# --- Frontend -----------------------------------------------------------------

frontend-install: ## установить pinned frontend-зависимости
	cd tools/frontend && npm ci --no-audit --no-fund

frontend-build: frontend-install ## собрать локальный Tailwind CSS
	cd tools/frontend && npm run build:css

frontend-audit: ## проверить caption/scope таблиц и отсутствие runtime CDN
	python3 bin/frontend-audit.py

# --- Качество кода ----------------------------------------------------------

lint: ## проверка синтаксиса всех PHP-файлов
	$(CONSOLE) dev:lint

audit: ## статический аудит кода (дубли классов, сигнатуры, контракты задач/миграций, view)
	$(CONSOLE) dev:audit

smoke: ## дымовой прогон маршрутов внутри процесса (ловит 500 на страницах): make smoke module=erp401_wms
	$(CONSOLE) dev:smoke $(if $(module),--module=$(module),)

framework: ## проверка, что папки фреймворка не изменены (Konine 3.4.0)
	$(CONSOLE) dev:framework

check: framework lint audit test smoke doctor docs-check ## полная быстрая проверка: фреймворк + линт + аудит + тесты + маршруты + окружение + документация
	@echo "Проверка завершена: ошибок нет"

docs-check: ## проверить, что сгенерированная документация совпадает с кодом
	$(CONSOLE) module:docs --check

docs: ## обновить сгенерированную документацию (README, ARCHITECTURE, MODULE_MAP)
	$(CONSOLE) module:docs

doctor: ## диагностика окружения (PHP, БД, конфиги, модули)
	$(CONSOLE) dev:doctor

modules: ## инвентаризация модулей
	$(CONSOLE) module:list

test: ## запустить тесты (фильтр по модулю: make test filter=Erp401)
	$(PHPUNIT) $(if $(filter),--filter=$(filter),)

test-coverage: ## тесты с покрытием (нужен xdebug/pcov)
	vendor/bin/phpunit -c tests/phpunit.xml.dist --coverage-html build/coverage

# --- Работа с одним модулем (экономно по токенам: не читаем весь репозиторий) ---

context: ## контекст одного модуля: make context module=erp401_wms
	@[ -n "$(module)" ] || { echo "Укажите модуль: make context module=erp401_wms"; exit 2; }
	$(CONSOLE) module:context --module=$(module)

check-module: ## проверки только этого модуля: make check-module module=erp401_wms
	@[ -n "$(module)" ] || { echo "Укажите модуль: make check-module module=erp401_wms"; exit 2; }
	$(CONSOLE) dev:lint --path=application/modules/$(module)
	$(CONSOLE) dev:audit --module=$(module)
	$(CONSOLE) dev:smoke --module=$(module)
	$(if $(MODULE_ID),$(PHPUNIT) --filter=Erp$(MODULE_ID),@echo "У модуля $(module) нет числового ID — тесты по фильтру не запускаю")

# --- Новые модули -----------------------------------------------------------

new-module: ## создать модуль: make new-module id=401 slug=tasks name="Задачи"
	$(CONSOLE) module:scaffold --id=$(id) --slug=$(slug) --name="$(name)"
