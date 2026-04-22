---
name: Операционные практики
type: index
description: Правила и гайды по операционным практикам: suppressions, фиксы, smoke-тесты
---

# Операционные практики

Собрание правил и гайдов, описывающих операционные практики проекта: suppressions статического анализа, исправления типичных проблем, smoke-тесты.

## Документы

- [Fixes](fixes.md) — типичные проблемы и их решения
- [Smoke Commands](smoke-commands.md) — команды для проверки работоспособности
- [Обоснованные подавления PHPMD](phpmd-suppressions-guidelines.md) — когда и как подавлять предупреждения PHPMD

## Примеры

Примеры конфигураций инструментов доступны в [examples/](../examples/):
- [phpmd.xml](../examples/phpmd.xml) — конфигурация PHPMD
- [phpunit.xml.dist](../examples/phpunit.xml.dist) — конфигурация PHPUnit
- [psalm.xml](../examples/psalm.xml) — конфигурация Psalm
- [phpcs.xml.dist](../examples/phpcs.xml.dist) — конфигурация PHP_CodeSniffer
- [Makefile](../examples/Makefile) — Makefile с командами проверок
