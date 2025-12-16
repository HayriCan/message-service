.PHONY: help up down restart logs build ps install migrate fresh seed key cache clear dispatch dispatch-reset dispatch-retry queue-restart queue-logs test test-unit test-feature test-coverage swagger shell tinker setup

# Default target
.DEFAULT_GOAL := help

# Colors
GREEN  := \033[0;32m
YELLOW := \033[0;33m
CYAN   := \033[0;36m
RESET  := \033[0m

## —— Docker ——————————————————————————————————————————————————————————
up: ## Start all containers
	docker-compose up -d

down: ## Stop all containers
	docker-compose down

restart: ## Restart all containers
	docker-compose restart

logs: ## Follow container logs
	docker-compose logs -f

build: ## Rebuild images without cache
	docker-compose build --no-cache

ps: ## Show container status
	docker-compose ps

## —— Laravel ——————————————————————————————————————————————————————————
install: ## Install composer dependencies
	docker-compose exec app composer install

migrate: ## Run database migrations
	docker-compose exec app php artisan migrate

fresh: ## Drop all tables and re-run migrations
	docker-compose exec app php artisan migrate:fresh

seed: ## Seed the database
	docker-compose exec app php artisan db:seed

key: ## Generate application key
	docker-compose exec app php artisan key:generate

cache: ## Cache config and routes
	docker-compose exec app php artisan config:cache
	docker-compose exec app php artisan route:cache

clear: ## Clear all caches
	docker-compose exec app php artisan cache:clear
	docker-compose exec app php artisan config:clear
	docker-compose exec app php artisan route:clear
	docker-compose exec app php artisan view:clear

## —— Messages ——————————————————————————————————————————————————————————
dispatch: ## Process pending messages
	docker-compose exec app php artisan messages:send

dispatch-reset: ## Reset stale messages and process
	docker-compose exec app php artisan messages:send --reset-stale

dispatch-retry: ## Retry failed messages
	docker-compose exec app php artisan messages:send --retry-failed

queue-restart: ## Restart queue worker
	docker-compose restart queue

queue-logs: ## Follow queue worker logs
	docker-compose logs -f queue

## —— Testing ——————————————————————————————————————————————————————————
test: ## Run all tests
	docker-compose exec app php artisan test

test-unit: ## Run unit tests only
	docker-compose exec app php artisan test --testsuite=Unit

test-feature: ## Run feature tests only
	docker-compose exec app php artisan test --testsuite=Feature

test-coverage: ## Run tests with coverage report
	docker-compose exec app php artisan test --coverage

## —— Documentation ————————————————————————————————————————————————————
swagger: ## Generate Swagger documentation
	docker-compose exec app php artisan l5-swagger:generate

## —— Utilities ————————————————————————————————————————————————————————
shell: ## Open bash shell in app container
	docker-compose exec app bash

tinker: ## Open Laravel Tinker
	docker-compose exec app php artisan tinker

setup: ## Initial setup (install, key, migrate, seed, swagger)
	@echo "$(CYAN)Installing dependencies...$(RESET)"
	docker-compose exec app composer install
	@echo "$(CYAN)Generating application key...$(RESET)"
	docker-compose exec app php artisan key:generate
	@echo "$(CYAN)Running migrations...$(RESET)"
	docker-compose exec app php artisan migrate
	@echo "$(CYAN)Seeding database...$(RESET)"
	docker-compose exec app php artisan db:seed
	@echo "$(CYAN)Generating Swagger docs...$(RESET)"
	docker-compose exec app php artisan l5-swagger:generate
	@echo "$(GREEN)Setup complete!$(RESET)"

## —— Help —————————————————————————————————————————————————————————————
help: ## Show this help
	@echo "$(YELLOW)Message Service - Available Commands$(RESET)"
	@echo ""
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  $(GREEN)%-18s$(RESET) %s\n", $$1, $$2}'
	@echo ""
