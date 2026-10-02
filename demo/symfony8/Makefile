.PHONY: help up down build install shell logs test test-coverage cache-clear link-bundle update update-bundle update-deps ensure-up restart release-check database test-e2e demo-screenshots assets

DOCKER_BIN := $(shell command -v docker 2>/dev/null)
ifeq ($(DOCKER_BIN),)
DOCKER_COMPOSE := docker-compose
else
DOCKER_COMPOSE := $(shell $(DOCKER_BIN) compose version >/dev/null 2>&1 && echo "$(DOCKER_BIN) compose" || echo "docker-compose")
endif
SERVICE_PHP := php

help:
	@echo "Symfony 8 Demo - Page Builder Kit Bundle"
	@echo "  URL:        http://localhost:8137"
	@echo "  Login:      /login (admin/admin)"
	@echo "  S3 mock:    http://localhost:9190 (Adobe S3Mock API, bucket page-builder-kit)"
	@echo "  Admin canvas home: /admin/page-builder/pages/home/canvas"
	@echo "  Admin canvas contact: /admin/page-builder/pages/contact/canvas"
	@echo "  make assets         pnpm + Vite (app.ts → public/build/) via Pentatrion"
	@echo "  make test-e2e       Playwright e2e (REQ-DEMO-013)"
	@echo "  make demo-screenshots  Refresh docs/images/demo/*.png"

up:
	@if [ ! -f .env ]; then cp .env.example .env 2>/dev/null || true; fi
	@if ! $(DOCKER_COMPOSE) images -q $(SERVICE_PHP) 2>/dev/null | grep -q .; then \
		$(DOCKER_COMPOSE) up -d --build; \
	else \
		$(DOCKER_COMPOSE) up -d; \
	fi
	@echo "Waiting for container to be ready..."
	@i=0; \
	until $(DOCKER_COMPOSE) exec -T php true 2>/dev/null; do \
		i=$$((i+1)); \
		if [ $$i -ge 30 ]; then \
			echo "Container failed to become ready; last logs:"; \
			$(DOCKER_COMPOSE) logs --tail 80 php || true; \
			exit 1; \
		fi; \
		sleep 2; \
	done
	@echo "Installing dependencies..."
	@$(DOCKER_COMPOSE) exec -T -e COMPOSER_MEMORY_LIMIT=-1 php composer install --no-interaction
	@$(MAKE) link-bundle
	@$(MAKE) assets
	@PORT=$$(grep "^PORT=" .env 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	[ -z "$$PORT" ] && PORT=$$(grep "^PORT=" .env.example 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	echo "Demo started at: http://localhost:$$PORT"

down:
	$(DOCKER_COMPOSE) down

restart: ensure-up
	$(DOCKER_COMPOSE) restart php
	$(DOCKER_COMPOSE) exec -T php sh -c "php bin/console doctrine:database:create --if-not-exists --no-interaction && php bin/console doctrine:schema:update --force --no-interaction"

build:
	$(DOCKER_COMPOSE) build --no-cache

ensure-up:
	@if [ ! -f .env ]; then cp .env.example .env 2>/dev/null || true; fi
	@if ! $(DOCKER_COMPOSE) exec -T php true 2>/dev/null; then $(DOCKER_COMPOSE) up -d; sleep 5; fi

install: ensure-up
	$(DOCKER_COMPOSE) exec -T -e COMPOSER_MEMORY_LIMIT=-1 php composer install --no-interaction
	@$(MAKE) assets

shell: ensure-up
	$(DOCKER_COMPOSE) exec php sh

logs:
	$(DOCKER_COMPOSE) logs -f

database: ensure-up
	$(DOCKER_COMPOSE) exec -T php sh -c "php bin/console doctrine:database:create --if-not-exists --no-interaction && php bin/console doctrine:schema:update --force --no-interaction"

test: ensure-up link-bundle
	$(DOCKER_COMPOSE) exec -e APP_ENV=test php sh -c "mkdir -p var/data && php bin/console doctrine:schema:update --force"
	$(DOCKER_COMPOSE) exec -e APP_ENV=test php composer test

test-coverage: ensure-up link-bundle
	$(DOCKER_COMPOSE) exec -e APP_ENV=test php composer test-coverage

cache-clear: ensure-up
	$(DOCKER_COMPOSE) exec php php bin/console cache:clear

link-bundle: ensure-up
	$(DOCKER_COMPOSE) exec php sh -c "rm -rf vendor/nowo-tech/page-builder-kit-bundle && ln -sf /var/page-builder-kit-bundle vendor/nowo-tech/page-builder-kit-bundle && php bin/console doctrine:database:create --if-not-exists --no-interaction && php bin/console doctrine:schema:update --force --no-interaction && php bin/console assets:install --symlink --relative --no-interaction && php bin/console cache:clear"

assets: ensure-up
	@# Prefer host pnpm when available (faster); fall back to container.
	@if command -v pnpm >/dev/null 2>&1; then \
		rm -rf public/build/.vite public/build/assets 2>/dev/null || true; \
		CI=true pnpm install && pnpm run build; \
	else \
		$(DOCKER_COMPOSE) exec -T -e CI=true php sh -c 'pnpm install && pnpm run build'; \
	fi

update: ensure-up
	$(DOCKER_COMPOSE) exec -T -e COMPOSER_MEMORY_LIMIT=-1 php composer update --no-interaction

update-bundle: ensure-up
	$(DOCKER_COMPOSE) exec php composer update nowo-tech/page-builder-kit-bundle --no-interaction
	@$(MAKE) link-bundle

release-check: ensure-up update-bundle test test-coverage

# Optional: monorepo helper absent on standalone GitHub Actions checkout.
-include $(abspath $(dir $(lastword $(MAKEFILE_LIST)))/../../../.scripts/Makefile.demo-update-deps.mk)

# Paste into demo/symfony8/Makefile (REQ-DEMO-013). Uses official Playwright image.
PLAYWRIGHT_IMAGE ?= mcr.microsoft.com/playwright:v1.63.0-jammy

test-e2e: ensure-up
	@mkdir -p e2e/node_modules
	@PORT=$$(grep -E '^PORT=' .env 2>/dev/null | cut -d= -f2); PORT=$${PORT:-8137}; \
	docker run --rm --network host \
		-v "$(CURDIR)/e2e:/e2e" -w /e2e \
		-e DEMO_BASE_URL=http://127.0.0.1:$$PORT \
		$(PLAYWRIGHT_IMAGE) \
		bash -lc 'npm install --no-fund --no-audit && npx playwright test --project=chromium'

demo-screenshots: ensure-up
	@mkdir -p ../../docs/images/demo e2e/node_modules
	@PORT=$$(grep -E '^PORT=' .env 2>/dev/null | cut -d= -f2); PORT=$${PORT:-8137}; \
	docker run --rm --network host \
		-v "$(CURDIR)/e2e:/e2e" \
		-v "$(CURDIR)/../../docs/images/demo:/screenshots" \
		-w /e2e \
		-e DEMO_BASE_URL=http://127.0.0.1:$$PORT \
		-e SCREENSHOT_DIR=/screenshots \
		$(PLAYWRIGHT_IMAGE) \
		bash -lc 'npm install --no-fund --no-audit && npx playwright test --project=screenshots'
	@echo "Screenshots written under docs/images/demo/"

