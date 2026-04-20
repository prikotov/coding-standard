include .env
-include .env.local

.DEFAULT_GOAL := help

COMPOSER_INSTALL_FLAGS := --no-interaction --no-progress

.PHONY: help
help: ## Показать помощь
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z0-9_-]+:.*?## / {printf "  \033[36m%-20s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)

# ==================
#  Static Analysis
# ==================

.PHONY: lint
lint: ## Запустить parallel-lint
	./vendor/bin/parallel-lint src tests apps/*/src

.PHONY: phpcs
phpcs: ## Проверить стиль кода
	vendor/bin/phpcs -q apps src tests

.PHONY: phpmd
phpmd: ## Запустить PHP Mess Detector
	vendor/bin/phpmd analyze src --format=text --ruleset=phpmd.xml

.PHONY: deptrac
deptrac: ## Запустить анализ зависимостей
	vendor/bin/deptrac analyse --config-file=depfile.yaml --no-progress

.PHONY: psalm
psalm: ## Запустить Psalm
	vendor/bin/psalm --no-cache --no-progress

# ==========
#  Tests
# ==========

.PHONY: tests-unit
tests-unit: ## Запустить unit-тесты
	bin/phpunit -c phpunit.xml.dist --testsuite unit

.PHONY: tests-integration
tests-integration: ## Запустить integration-тесты
	bin/phpunit -c phpunit.xml.dist --testsuite integration

.PHONY: tests-e2e
tests-e2e: ## Запустить E2E-тесты
	bin/phpunit -c phpunit.xml.dist --testsuite e2e

# ==========
#  CI
# ==========

.PHONY: check
check: ## Запустить все проверки
	@${MAKE} --no-print-directory phpcs phpmd deptrac psalm tests-unit tests-integration && \
		{ echo; echo "✅ Все проверки завершены успешно."; } || \
		{ echo; echo "❌ Проверки завершены с ошибками."; exit 1; }

# ==========
#  Install
# ==========

.PHONY: install
install: ## Запустить composer install
	composer install $(COMPOSER_INSTALL_FLAGS)
