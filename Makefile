SHELL := /bin/bash

COMPOSE := env UID=$$(id -u) docker compose -f docker/docker-compose.yaml
PHP := $(COMPOSE) run --rm --entrypoint php ezdeliver-php
IMAGE := ghcr.io/baptistecontreras/ez-delivery

.DEFAULT_GOAL := help
.PHONY: help build up down shell install test cs-fix cs-check prod-build gh-login gh-logout gh-push gh-push-latest

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

gh-login: ## Log in to ghcr.io (GitHub Container Registry)
	@read -p "GitHub username: " gh_user; \
	read -s -p "GitHub token (PAT with write:packages scope): " gh_token; \
	echo; \
	echo "$$gh_token" | docker login ghcr.io -u "$$gh_user" --password-stdin

gh-logout: ## Log out of ghcr.io (GitHub Container Registry)
	@docker logout ghcr.io

gh-push: ## Tag a local image and push it to ghcr.io (prompts for source local tag and destination tag, override with SOURCE= and TAG=)
	@if [ -z "$(SOURCE)" ]; then \
		read -p "Local tag to push (e.g. $(IMAGE):2.3.0): " source; \
	else \
		source="$(SOURCE)"; \
	fi; \
	if [ -z "$$source" ]; then echo "No source tag provided, aborting."; exit 1; fi; \
	if [ -z "$(TAG)" ]; then \
		read -p "Tag to use on ghcr.io (e.g. 2.3.0): " tag; \
	else \
		tag="$(TAG)"; \
	fi; \
	if [ -z "$$tag" ]; then echo "No destination tag provided, aborting."; exit 1; fi; \
	read -p "Push $$source as $(IMAGE):$$tag ? [y/N]: " confirm; \
	if [ "$$confirm" != "y" ] && [ "$$confirm" != "Y" ]; then echo "Aborted."; exit 1; fi; \
	docker tag "$$source" $(IMAGE):$$tag; \
	docker push $(IMAGE):$$tag

gh-push-latest: ## Tag a local image as :latest on ghcr.io and push it (prompts for source local tag, override with SOURCE=)
	@if [ -z "$(SOURCE)" ]; then \
		read -p "Local tag to tag and push as latest (e.g. $(IMAGE):2.3.0): " source; \
	else \
		source="$(SOURCE)"; \
	fi; \
	if [ -z "$$source" ]; then echo "No source tag provided, aborting."; exit 1; fi; \
	read -p "Push $$source as $(IMAGE):latest ? [y/N]: " confirm; \
	if [ "$$confirm" != "y" ] && [ "$$confirm" != "Y" ]; then echo "Aborted."; exit 1; fi; \
	docker tag "$$source" $(IMAGE):latest; \
	docker push $(IMAGE):latest
