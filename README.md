# Стандарт кодирования и обеспечения качества для PHP-проектов

**`prikotov/coding-standard`** — PHP-пакет с тремя частями:

1. **Конвенции** — документация DDD-конвенций (`docs/conventions/`), копируемая в проект-потребитель через `bin/coding-standard-init`.
2. **`PHPCS`-сниффы** — автоматические проверки соблюдения конвенций через PHP CodeSniffer 4.x (`src/`).
3. **Метрики качества** — дают ИИ-агенту воспроизводимые данные для оценки изменений структуры и связанности подключаемого проекта при код-ревью; результат также может формироваться в виде автономного HTML-дашборда.

Конвенции — основа пакета и источник правил для команды и ИИ-агентов.
Автоматические проверки и метрики дают детерминированную обратную связь:
проверки выявляют нарушения формализуемых конвенций, а метрики показывают
изменение структуры и связанности кода. Вместе они замыкают петлю обратной
связи (feedback loop) до финального код-ревью. Если ревью проводит человек
(human in the loop), до него доходит меньше проблем и снижается нагрузка; без
участия человека уменьшается вероятность незаметного ухудшения структуры и
появления плохо поддерживаемого кода.

---

## Вектор развития

Конвенции остаются основой пакета. Развитие направлено на то, чтобы:

- расширять и уточнять DDD-конвенции как единый источник правил для разработчиков и ИИ-агентов;
- переносить формализуемые правила в автоматические проверки `PHPCS`, PHPStan и Deptrac, чтобы нарушения обнаруживались до код-ревью;
- развивать инструменты воспроизводимого сбора и сравнения метрик качества: предоставлять машиночитаемые данные для ИИ-агентов и автоматизации, а разработчикам — понятные отчёты.

Пакет будет описывать рекомендуемые сценарии применения метрик при код-ревью,
но способ их интеграции в процесс разработки выбирает проект-потребитель.

---

## Конвенции

Документация описывает принципы, паттерны, слои, модули, тестирование и структуру Symfony-приложения. Служит справочником для команды и ИИ-агентов.

Полное содержание — в [индексе конвенций](docs/conventions/index.md).

---

## Автоматические проверки

Соблюдение формализуемых конвенций проверяется через PHP CodeSniffer, PHPStan
и Deptrac до ручного код-ревью.

### Markdown-валидация

Проверка документации ведётся тремя инструментами:

- **`composer validate-docs`** — проверяет конвенции внутри каталога `docs/conventions/`: структуру front matter, именование файлов (kebab-case), обязательные секции и ссылки между документами каталога.
- **`composer validate-md-links`** — проверяет ссылки между Markdown-файлами всего проекта (пути и якоря). Область проверки настраивается через файл конфигурации `.md-links.php`. [Подробнее](docs/conventions/ops/validate-md-links.md).
- **`composer validate-language`** — ищет английские фразы в русскоязычном тексте Markdown/text-файлов (англицизмы вида «persisted rows»). Техническая терминология и code blocks исключаются. Настраивается через секцию `language` в `.coding-standard.php`. [Подробнее](docs/conventions/ops/validate-language.ru.md).


### PHP CodeSniffer-сниффы

| Снифф | Что проверяет |
|---|---|
| `DtoStructureSniff` | DTO — `final readonly`, только promoted-параметры в конструкторе, без методов и свойств |
| `EnumStructureSniff` | Enum — чистый (без методов, констант, трейтов), case'ы в `camelCase` |
| `ValueObjectStructureSniff` | Value Object — `final readonly`, неизменяемый, приватный конструктор, статические фабрики |
| `CommandQueryStructureSniff` | Command/Query — конструктор только с promoted-параметрами, без свойств и методов |
| `CommandHandlerStructureSniff` | `CommandHandler` — только `__invoke`, без публичных свойств |
| `QueryHandlerReturnTypeSniff` | `QueryHandler` — должен возвращать `Result` или `ResultDto` |
| `CommandHandlerReturnTypeSniff` | `CommandHandler` — должен возвращать `void` или `Result` |
| `UseCaseNamingSniff` | UseCase — обязательный суффикс; имя файла и неймспейс совпадают с путём |
| `GlobalFunctionCallStyleSniff` | Глобальные функции вызываются без обратного слеша и без `use function` |

### Deptrac-правила

| Правило | Что проверяет |
|---|---|
| `ServiceContractDependencyRule` | Infrastructure зависит только от Domain-интерфейсов, не от конкретных классов |
| `CrossModuleDomainRule` | Домен одного модуля не зависит от домена другого — только через Application DTO |

Готовый `depfile.yaml` с правилами для DDD-слоёв и модульных границ: [`config/deptrac/`](config/deptrac/). Копируется в проект через `coding-standard-init` или вручную.

### PHPStan-правила

Пользовательское PHPStan-расширение (Collector + Rule) для межфайловых проверок:

| Правило | Что проверяет |
|---|---|
| `DtoReuseRule` | Находит DTO в общей папке модуля (`Module\{ModuleName}\Application\Dto`), которые по факту использует только один use case, и предлагает переложить их рядом с владельцем. |
| `MessageContractDtoLocationRule` | Проверяет расположение DTO, используемых в контрактах Command и Query. |
| `ForbiddenInvokableHandlerCallRule` | Запрещает прямой вызов Command Handler и Query Handler как вызываемого объекта. |
| `ForbiddenExplicitHandlerInvokeRule` | Запрещает прямой вызов метода `__invoke()` у Command Handler и Query Handler. |

Потребитель добавляет `phpstan/phpstan` в `require-dev` и подключает правила пакета в конфигурации PHPStan:

```neon
includes:
    - vendor/prikotov/coding-standard/phpstan-rules.neon
```

Подробные варианты подключения описаны в разделе [«Подключение PHPStan»](#подключение-phpstan).

Конвенция размещения DTO: [`docs/conventions/core-patterns/dto.md`](docs/conventions/core-patterns/dto.md).

Примеры конфигураций: [`docs/conventions/examples/`](docs/conventions/examples/)

| Файл | Назначение |
|---|---|
| `phpcs.xml.dist` | PHP CodeSniffer |
| `phpunit.xml.dist` | PHPUnit |
| `phpmd.xml` | PHPMD |
| `phpstan.neon.dist` | PHPStan |
| `psalm.xml` | `Psalm` |
| `Makefile` | Команды проверки (`make check`) |

---

## Метрики качества подключаемого проекта

Инструмент собирает воспроизводимый снимок продуктового PHP-кода и показывает:

- размер и сложность методов и классов;
- классы с несколькими несвязанными группами методов;
- входящие и исходящие зависимости классов;
- размер, внешнюю связанность и циклические зависимости модулей;
- размер кодовой базы, объём тестов и покрытие.

JSON-отчёт даёт ИИ-агентам и автоматизации структурированные данные для анализа
изменений. Автономный HTML-дашборд помогает разработчику увидеть проблемные
области и выбрать кандидатов для рефакторинга.

После установки пакета и подготовки конфигурации отчёт собирается из корня
анализируемого проекта:

```bash
vendor/bin/coding-standard-init --project-name=ProjectName
vendor/bin/coding-standard-metrics --update-snapshot
git add .coding-standard/metrics/
```

Команда обновляет отслеживаемое зеркало `.coding-standard/metrics/` и строит
неотслеживаемый HTML в `var/metrics/index.html`. Временные входы анализаторов
остаются в `var/metrics/`. В CI снимок проверяется без записи:

```bash
vendor/bin/coding-standard-metrics --check-snapshot
```

Две совместимые версии снимка сравниваются без повторного анализа базовой
ревизии:

```bash
vendor/bin/coding-standard-metrics-compare \
  --baseline=/tmp/metrics-baseline \
  --current=.coding-standard/metrics \
  --output=var/metrics-review
```

Команда создаёт детерминированные `comparison.json` для ИИ-агента и
`summary.md` для разработчика. Опция `--changed-paths=/tmp/changed-paths.txt`
помечает объекты из текущего Git diff. Несовпадение проекта, схемы, версии
определений, конфигурации или версий источников завершает сравнение ошибкой.

Для код-ревью полный артефакт строится из корня проекта одной командой:

```bash
vendor/bin/coding-standard-metrics-review \
  --base=origin/master \
  --head=HEAD \
  --output=var/metrics-review
```

Команда сначала запускает `--check-snapshot`, затем извлекает снимок merge-base
из Git без checkout и сохраняет:

- `baseline/.coding-standard/metrics/` и `current/.coding-standard/metrics/`;
- `comparison.json` и `summary.md`;
- `reproduction.json` с commit базовой ветки, merge-base, HEAD и отпечатками входов.

Пример job для GitHub Actions находится в
[`docs/conventions/examples/github-actions-metrics-review.yml`](docs/conventions/examples/github-actions-metrics-review.yml).
Он публикует весь каталог как artifact, а Markdown — в job summary. В ревью
агент обязан связать регрессии из `comparison.json` с текущим diff. Необъяснённая
регрессия в изменённой области блокирует одобрение; допустимое ухудшение явно
обосновывается в PR.

Рекомендуемые команды проекта-потребителя:

```json
{
  "scripts": {
    "metrics": "vendor/bin/coding-standard-metrics --update-snapshot",
    "metrics:check": "vendor/bin/coding-standard-metrics --check-snapshot",
    "metrics:review": "vendor/bin/coding-standard-metrics-review"
  }
}
```

Устаревший параметр `metrics.report_dir` нужно переименовать в
`metrics.work_dir`. Канонический путь не настраивается. Корневой `.gitignore`
должен исключать `/var/`, но не `.coding-standard/metrics/`.

Для полного отчёта нужны Deptrac, PHPUnit, `scc` и PCOV. Модель данных,
настройка и правила интерпретации описаны в
[конвенции метрик качества](docs/conventions/ops/quality-metrics.md).

### Пример HTML-дашборда

Пример автономного HTML-дашборда на данных проекта TasK:
[`examples/task-metrics-dashboard.html`](examples/task-metrics-dashboard.html).

#### Размер модулей и зависимости от других модулей

![Размер модулей и зависимости от других модулей](docs/images/metrics-dashboard/modules-size-and-dependencies.webp)

#### Классы: размер и недостаток связности методов (LCOM4)

![Классы: размер и недостаток связности методов](docs/images/metrics-dashboard/classes-size-and-lcom4.webp)

#### Матрица зависимостей модулей

![Матрица зависимостей модулей](docs/images/metrics-dashboard/module-dependency-matrix.webp)

---

## CLI-утилиты

Публичные команды пакета доступны через `vendor/bin/`.

| Команда | Назначение |
|---|---|
| `coding-standard-init` | Копирует конвенции и конфигурации в проект |
| `validate-md-links` | Проверяет ссылки и якоря Markdown |
| `validate-language` | Проверяет англицизмы в русскоязычной документации |
| `coding-standard-metrics` | Обновляет или проверяет JSON-снимок и строит HTML-дашборд подключаемого проекта |
| `coding-standard-metrics-compare` | Сравнивает совместимые снимки и создаёт JSON/Markdown с дельтами |
| `coding-standard-metrics-review` | Проверяет current, извлекает baseline из Git и собирает артефакт PR |
| `metrics-collect` | Собирает структурные метрики PHP-кода |
| `metrics-scc` | Собирает размер кодовой базы и версию `scc` |
| `metrics-coverage` | Создаёт Clover-отчёт покрытия через PHPUnit и PCOV |
| `test-stats` | Считает файлы и строки по сьютам PHPUnit |

---

## Установка

```bash
composer require --dev prikotov/coding-standard
```

Скопируйте конвенции и конфигурации в проект:

```bash
php vendor/bin/coding-standard-init --project-name=ProjectName
```

Команда `coding-standard-init` копирует конвенции, конфигурации и шаблоны
типовых исключений с подстановкой пространства имён проекта. Сниффы и другие
инструменты пакета запускаются из `vendor/`.

### Подключение `PHPCS`

```xml
<config name="installed_paths" value="vendor/prikotov/coding-standard"/>
<rule ref="PrikotovCodingStandard"/>
```

### Подключение PHPStan

Добавьте PHPStan в проект, если он ещё не установлен:

```bash
composer require --dev phpstan/phpstan
```

Рекомендуемый вариант — явно подключить правила пакета в `phpstan.neon` или `phpstan.neon.dist`:

```neon
includes:
    - vendor/prikotov/coding-standard/phpstan-rules.neon
```

Явное подключение не зависит от Composer-плагинов и гарантирует применение правил после обновления пакета.

Альтернативный вариант — автоматическое подключение через `phpstan/extension-installer`:

```bash
composer config allow-plugins.phpstan/extension-installer true
composer require --dev phpstan/extension-installer
```

При автоматическом подключении добавлять `phpstan-rules.neon` в `includes` не нужно. Без одного из этих двух вариантов
пользовательские PHPStan-правила пакета не выполняются.

### Копирование конвенций в проект

```bash
php vendor/bin/coding-standard-init
```

По умолчанию существующие файлы не перезаписываются. Флаг `--force` включает перезапись.

```bash
php vendor/bin/coding-standard-init /path/to/project --docs-path=docs/ddd --deptrac-path=config/depfile.yaml --force
```

### Копирование типовых исключений

Шаблоны исключений хранятся в `config/exceptions/` и копируются в проект с подстановкой имени namespace.

```bash
php vendor/bin/coding-standard-init --project-name=Task
```

Это создаст файлы в `src/Common/Exception/` с namespace `Task\Common\Exception`.

| Опция | Описание |
|---|---|
| `--project-name=Task` | Имя проекта для namespace (обязательно для исключений) |
| `--exceptions-path=src/Common/Exception` | Путь копирования (по умолчанию) |
| `--no-exceptions` | Пропустить копирование исключений |

Без `--project-name` исключения пропускаются, остальные файлы копируются как обычно.

---

## Обновление

Обновите пакет в пределах версии, разрешённой в `composer.json`:

```bash
composer update prikotov/coding-standard --with-dependencies
```

Для перехода на следующую минорную версию до `1.0` обновите ограничение явно. Например, `^0.26` не разрешает установку
`0.27`:

```bash
composer require --dev prikotov/coding-standard:^0.27 --with-all-dependencies
```

Обновите скопированные конвенции и обязательную конфигурацию пакета:

```bash
php vendor/bin/coding-standard-init --force
```

Флаг `--force` перезаписывает ранее скопированные конвенции и `.coding-standard.php`. Проверьте изменения через
`git diff` и верните проектные настройки, если они отличаются от стандартных. Конфигурации `depfile.yaml`,
`phpcs.xml.dist` и `phpstan.neon.dist`, уже существующие в проекте, init-команда не перезаписывает.

После обновления запустите проверки проекта, включая PHPStan:

```bash
vendor/bin/phpstan analyse
composer check
```

---

## Лицензия

[Лицензия `MIT`](LICENSE)
