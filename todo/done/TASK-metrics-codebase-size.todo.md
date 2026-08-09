---
type: feat
created: 2026-08-02
value: V2
complexity: C2
priority: P2
depends_on:
epic: EPIC-metrics-ai-maintainability
author: pi
assignee: Разработчик (codex)
branch: task/metrics-codebase-size
pr: https://github.com/prikotov/coding-standard/pull/92
status: done
---

# TASK-metrics-codebase-size: Метрика размера кодовой базы (scc), статистика тестов и покрытие

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)

- Модель метрик (TASK-metrics-model-convention) покрывает классы и модули, но не размер всей кодовой базы: LOC за пределами src/ (bin, tests, docs, конфиги) не учитывается.
- Нет статистики тестов (число файлов, строк, среднее по сьютам) и покрытия — агенты не могут оценить объём тестов и доверие к ним.
- Эталоны есть на проекте TasK: Go-утилита scc (обёртка bin/scc-clean.sh), composer metrics-test-stats, make coverage-unit / coverage-integration (phpunit + pcov → clover.xml).

### Варианты или путь решения (Solution Sketch)

- Размер кодовой базы: `scc --format json` (с exclude vendor) → блок codebase size в report.json: LOC и файлы по языкам и по модулям пакета.
- Статистика тестов: скрипт по образцу bin/test-stats.sh из TasK — файлы/строки/среднее по сьютам, адаптированный под структуру tests/ этого пакета.
- Покрытие: composer script по образцу make coverage-unit / coverage-integration из TasK (php -d pcov.enabled=1 phpunit --coverage-clover ... --coverage-text ...); парсинг clover.xml (покрытие строк и методов) в report.json.
- Обновить модель метрик (docs/conventions/ops/quality-metrics.md из TASK-metrics-model-convention): project-level метрики — размер кодовой базы, тестовая статистика, покрытие.
- Расширить агрегатор (TASK-metrics-aggregator): новые секции report.json — codebase, tests, coverage.

### Ожидаемый результат (Expected Result)

- report.json содержит блоки: размер кодовой базы (по языкам и модулям), статистика тестов (файлы/строки/среднее по сьютам), покрытие (строки и методы, %).
- Команды сбора задокументированы; шаги включены в пайплайн composer metrics (через TASK-metrics-composer-integration).

## 1. Concept and Goal (Концепция и Цель)

### Story (User Story)

> Как ИИ-агент, я хочу видеть в отчёте размер кодовой базы, объём тестов и покрытие, чтобы оценивать масштаб изменений и доверие к тестам.

### Goal (Цель по SMART)

Добавить в пайплайн метрик три источника: scc (JSON-вывод размера кодовой базы), статистику тестов (по образцу bin/test-stats.sh из TasK) и покрытие из clover.xml (phpunit + pcov, по образцу make coverage-unit / coverage-integration из TasK); расширить report.json (TASK-metrics-aggregator) и модель метрик (TASK-metrics-model-convention) секциями codebase / tests / coverage. Проверка: `composer test` и `composer validate-todo` зелёные.

## 2. Context and Scope (Контекст и Границы)

- **Где делаем:** bin/ (скрипт статистики тестов, шаги сбора), composer.json (script покрытия), агрегатор — парсинг scc JSON и clover.xml, docs/conventions/ops/quality-metrics.md (обновление модели), var/metrics/ (выход).
- **Эталоны из TasK (Development/):** bin/scc-clean.sh (обёртка `scc --exclude-dir`), bin/test-stats.sh (файлы/строки/среднее по сьютам Unit/Integration/E2E), devops/make/tests.mk — targets coverage-unit / coverage-integration (`php -d pcov.enabled=1 bin/phpunit --testsuite unit|integration --coverage-clover tests/_output/coverage/clover.xml --coverage-text=php://stdout --only-summary-for-coverage-text --no-progress`).
- **Текущее поведение:** размер кодовой базы, статистика тестов и покрытие в отчёте метрик отсутствуют.
- **Границы (Out of Scope):**
  - Не переносим скрипты TasK как есть (структура tests/ + apps/*/tests чужда этому пакету) — адаптируем.
  - scc — внешний Go-бинарник: не добавляем в composer-зависимости и в composer check.
  - Покрытие не включаем в composer check (расширение pcov не гарантировано в CI).
  - E2E-сьюта в пакете нет — не добавляем.

## 3. Requirements (Требования, MoSCoW)

### 🔴 Must Have (Обязательно)

- [ ] Команда сбора размера кодовой базы: `scc --format json` (с exclude vendor) в var/metrics/scc.json; установка scc задокументирована (go install / prebuilt binary).
- [ ] Статистика тестов: число файлов, строк и среднее по сьютам (адаптация bin/test-stats.sh из TasK под структуру tests/ пакета).
- [ ] Покрытие: composer script (pcov + `--coverage-clover var/metrics/clover.xml` + text-summary); парсинг clover.xml — покрытие строк и методов.
- [ ] Агрегатор: секции codebase size (LOC по языкам и по модулям пакета), tests (файлы/строки/среднее по сьютам), coverage (строки и методы, %) в report.json.
- [ ] Модель метрик (quality-metrics.md из TASK-metrics-model-convention): добавлены project-level метрики — размер кодовой базы, статистика тестов, покрытие.

### 🟡 Should Have (Желательно)

- [ ] Секция codebase size по модулям пакета (src/*, bin, tests) — согласована с module-level моделью.
- [ ] Отсутствие `scc` или PCOV останавливает сбор метрик с понятной ошибкой: размер кодовой базы и покрытие обязательны для полного project-level отчёта.

### ⚫ Won't Have (Не будем делать)

- [ ] Не переносим скрипты TasK как есть (структура tests/ + apps/*/tests).
- [ ] Не добавляем scc/pcov в composer-зависимости и в composer check.
- [ ] Не добавляем E2E-сьют (в пакете его нет).

## 4. Implementation Plan (План реализации)

- Добавить необязательные входы `scc`, статистики тестов и Clover в агрегатор и сохранить их в корневом отчёте.
- Добавить `bin/metrics-scc`, `bin/test-stats` и Composer-скрипты без включения PCOV и scc в `composer check`.
- Покрыть преобразование данных агрегатора тестом и обновить модель метрик.

## 5. Definition of Done (Критерии приёмки)

- [x] scc-вывод и clover.xml собираются командами из Verification; агрегатор включает секции codebase / tests / coverage в report.json.
- [x] Модель метрик (quality-metrics.md) обновлена project-level метриками; `composer validate-docs` проходит.
- [x] `composer test` и `composer validate-todo` зелёные.

## 6. Verification (Самопроверка)

```bash
# размер кодовой базы (Go-бинарник scc)
bin/metrics-scc

# покрытие (требуется расширение pcov)
composer coverage

# статистика тестов
composer metrics-test-stats

# агрегация с новыми источниками
php bin/metrics-aggregate.php --analyzer=var/metrics/phpmetrics.json --deptrac=var/metrics/deptrac.json --scc=var/metrics/scc.json --tests=var/metrics/test-stats.json --clover=var/metrics/clover.xml --output=var/metrics/report.json
```

## 7. Risks and Dependencies (Риски и зависимости)

- scc — обязательный внешний бинарник для контура метрик; перед сбором его необходимо установить отдельно.
- PCOV — обязательное расширение окружения сбора метрик, но не окружения `composer check`.
- Разные версии scc дают разные цифры — зафиксировать версию в метаданных отчёта.
- bin/test-stats.sh из TasK завязан на структуру tests/ + apps/*/tests — нужна адаптация, не копирование.
- Зависимости: TASK-metrics-model-convention (схема), TASK-metrics-aggregator (расширение парсера и отчёта).

## 8. Sources (Источники)

- [scc — boyter/scc](https://github.com/boyter/scc)
- TasK (Development/): `bin/scc-clean.sh`, `bin/test-stats.sh`, `devops/make/tests.mk` (targets coverage-unit / coverage-integration)
- TASK-metrics-model-convention — модель метрик
- TASK-metrics-aggregator — report.json

## 9. Comments (Комментарии)

- На TasK scc вызывается через обёртку bin/scc-clean.sh (`scc --exclude-dir ...`); покрытие — phpunit + pcov с clover.xml и text-summary (make coverage-unit / coverage-integration).
- Размер кодовой базы — project-level метрика; дополняет class-level / module-level модель из TASK-metrics-model-convention.

## `Change History` (История изменений)

| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-08-02 | pi (Pi Coding Agent) | Создание задачи по итогам обсуждения с пользователем: метрика размера кодовой базы через scc, статистика тестов и покрытие по образцу инструментов проекта TasK. |
| 2026-08-08 | codex (Codex) | Выполненная TASK-metrics-model-convention удалена из depends_on. |
| 2026-08-08 | codex (Codex) | Подтверждён план реализации; задача переведена в работу. |
| 2026-08-08 | codex (Codex) | Реализованы источники scc, статистики тестов и Clover; PR открыт на ревью. |
| 2026-08-09 | codex (Codex) | Учтены замечания ревью: опциональный PCOV, версия scc, сьюты PHPUnit, SimpleXML и команды проверки. |
| 2026-08-09 | codex (Codex) | Сбор статистики тестов вынесен в тестируемый класс; команда приведена к формату остальных PHP-команд в `bin/`. |
| 2026-08-09 | codex (Codex) | В README добавлены назначение CLI-утилит, команды сбора project-level метрик и внешние зависимости. |
| 2026-08-09 | codex (Codex) | Статистика тестов закреплена как обязательный источник; опциональными оставлены только зависящие от `scc` и PCOV метрики. |
| 2026-08-09 | codex (Codex) | По решению пользователя `scc` сделан обязательным источником для проверки полноты структурного отчёта; опциональным осталось только покрытие. |
| 2026-08-09 | codex (Codex) | По решению пользователя покрытие также сделано обязательной project-level метрикой; окружение сбора требует PCOV. |
| 2026-08-09 | codex (Codex) | Изменения приняты пользователем; задача завершена перед merge PR. |
