.DEFAULT_GOAL := help

include Makefile.setup

dc = docker compose
composer = $(dc) run -eXDEBUG_MODE=off --rm --no-deps php composer
php = $(dc) run --rm --no-deps php php

.PHONY: help
help: ## print documented targets
	@egrep -h '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort -n | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-24s\033[0m %s\n", $$1, $$2}'

.PHONY: vendor
vendor: composer.json ## install composer dependencies
	@$(composer) install --no-progress

.PHONY: composer-validate
composer-validate: ## validate composer.json
	@$(composer) validate --strict

.PHONY: test
test: ## run tests
	@$(php) vendor/bin/phpunit --testdox

.PHONY: static
static: ## run static analysis
	@$(php) vendor/bin/phpstan analyse -c phpstan.dist.neon --no-progress -v

.PHONY: cs
cs: ## run php-cs-fixer in check mode
	@$(dc) run -ePHP_CS_FIXER_IGNORE_ENV=1 --rm --no-deps php vendor/bin/php-cs-fixer check --using-cache=no --show-progress=none

.PHONY: fix
fix: ## fix code style
	@$(dc) run -ePHP_CS_FIXER_IGNORE_ENV=1 --rm --no-deps php vendor/bin/php-cs-fixer fix

.PHONY: check
check: composer-validate cs static test ## run all local checks

.PHONY: pre-commit-install
pre-commit-install: ## install git hooks via pre-commit
	@pre-commit install --install-hooks
	@pre-commit install --hook-type pre-push --install-hooks

.PHONY: pre-commit-run
pre-commit-run: ## run all pre-commit hooks
	@pre-commit run --all-files
