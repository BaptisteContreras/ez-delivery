COMPOSE := UID=$$(id -u) docker compose -f docker/docker-compose.yaml
PHP := $(COMPOSE) run --rm --entrypoint php ezdeliver-php
IMAGE := ghcr.io/baptistecontreras/ez-delivery

.DEFAULT_GOAL := help
.PHONY: help build up down shell install test cs-fix cs-check prod-build

help: ## Show this help
	@echo "Available targets:"
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  %-12s %s\n", $$1, $$2}'

build: ## Build the dev PHP docker image
	$(COMPOSE) build

up: ## Launch the dev stack in the background
	$(COMPOSE) up -d

down: ## Stop the dev stack
	$(COMPOSE) down

shell: ## Open a shell in the running dev container
	$(COMPOSE) exec ezdeliver-php bash

install: ## Run composer install
	$(PHP) /usr/local/bin/composer install

test: ## Run the PHPUnit test suite
	$(PHP) /app/vendor/bin/phpunit

cs-fix: ## Run PHP CS Fixer and apply fixes
	$(PHP) /app/vendor/bin/php-cs-fixer fix

cs-check: ## Run PHP CS Fixer in dry-run mode
	$(PHP) /app/vendor/bin/php-cs-fixer fix --dry-run --diff

prod-build: ## Build the prod image, tagged with a version (prompts if VERSION is not set)
	@if [ -z "$(VERSION)" ]; then \
		read -p "Version tag to build (e.g. 2.3.0): " version; \
	else \
		version="$(VERSION)"; \
	fi; \
	if [ -z "$$version" ]; then \
		echo "No version tag provided, aborting."; \
		exit 1; \
	fi; \
	docker build --target prod -f prod/Dockerfile -t $(IMAGE):$$version -t $(IMAGE):latest .
