SHELL := /bin/bash
.NOTPARALLEL:
export LOCAL_UID := $(shell id -u)
export LOCAL_GID := $(shell id -g)
COMPOSE := docker compose --env-file .env.local
RUN := $(COMPOSE) run --rm --no-deps app

.PHONY: init install db-up migrate fixtures up down token analyse style-check style-fix validate audit docker-build
init:
	python3 bin/init-local
install:
	$(COMPOSE) build app
	$(RUN) composer install --no-interaction --prefer-dist
db-up:
	$(COMPOSE) up -d --wait db
migrate:
	$(COMPOSE) run --rm migrate php bin/console doctrine:migrations:migrate --no-interaction
fixtures:
	$(RUN) php bin/console app:demo:seed
up:
	$(COMPOSE) up -d app web worker
down:
	$(COMPOSE) down
token:
	$(RUN) php bin/console app:token:issue "$$ACTOR"
analyse:
	$(TEST) run --rm test vendor/bin/phpstan analyse --no-progress
style-check:
	$(RUN) vendor/bin/php-cs-fixer check --diff
style-fix:
	$(RUN) vendor/bin/php-cs-fixer fix
validate:
	$(RUN) composer validate --strict
	$(TEST) run --rm test php bin/console lint:container
	$(TEST) run --rm test php bin/console lint:yaml config
	$(TEST) run --rm test php bin/console doctrine:schema:validate
	$(RUN) sh -c 'find src tests config migrations public -name "*.php" -print0 | xargs -0 -n1 php -l >/dev/null'
	docker compose --env-file .env.local config --quiet
	docker compose --env-file .env.local -f compose.test.yaml config --quiet
audit:
	$(RUN) composer audit --locked
docker-build:
	docker build --target production -f docker/Dockerfile -t equipment-service-desk:local .
TEST = docker compose --env-file .env.local -f compose.test.yaml
test-unit test-integration test-api:
	$(TEST) run --rm test sh bin/test-suite --testsuite $(patsubst test-%,%,$@)
docs-check:
	$(TEST) run --rm test php bin/docs-check.php
	python3 -c 'import ast,pathlib; [ast.parse(p.read_text()) for p in pathlib.Path("bin").iterdir() if p.is_file() and p.read_text().startswith("#!/usr/bin/env python3")]'
	python3 -m unittest discover -s tests/Tools -v
	sh -n bin/test-suite docker/init-db.sh
scan:
	python3 bin/scan
walkthrough:
	python3 bin/walkthrough
smoke:
	python3 bin/smoke
check:
	$(MAKE) test-unit test-integration test-api analyse style-check validate docs-check audit scan walkthrough docker-build smoke
	git diff --check
	git diff --cached --check
