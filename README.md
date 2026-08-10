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

Инструменты метрик анализируют PHP-проект, из корня которого они запущены. В
проекте-потребителе они дают ИИ-агенту воспроизводимые данные о структуре,
связанности, размере, тестах и покрытии для использования при код-ревью.
Модель и расшифровка показателей описаны в
[конвенции метрик качества](docs/conventions/ops/quality-metrics.md).

Машиночитаемый JSON-отчёт предназначен для автоматической обработки и работы
ИИ-агента. Автономный HTML-дашборд — дополнительное представление тех же данных
для человека.

### Требования

В проекте должны быть установлены пакет, Deptrac и PHPUnit с командой
`vendor/bin/phpunit`:

```bash
composer require --dev prikotov/coding-standard deptrac/deptrac phpunit/phpunit
```

`require-dev` зависимостей пакета не наследуется проектом, поэтому Deptrac и
PHPUnit должны присутствовать в его собственном `composer.json`. Если PHPUnit
уже установлен напрямую или другим совместимым способом, повторно добавлять
его не нужно.

Для размера кодовой базы требуется `scc`, для покрытия — расширение PCOV:

```bash
go install github.com/boyter/scc/v3@latest
pecl install pcov
```

Бинарник `scc` должен находиться в `PATH`, а `php -m` — показывать `pcov`.

### Настройка проекта

Для нового подключения создайте `.coding-standard.php` и `depfile.yaml`:

```bash
vendor/bin/coding-standard-init --project-name=ProjectName
```

В существующем проекте проверьте секцию `metrics` файла
`.coding-standard.php`:

```php
<?php

declare(strict_types=1);

return [
    'docs_path' => 'docs/conventions',
    'metrics' => [
        'report_dir' => 'var/metrics',
        'deptrac_config' => 'depfile.yaml',
        'phpunit_config' => 'phpunit.xml.dist',
        'exclude' => [
            'vendor/', '.git/', 'var/', 'tmp/', 'packages/',
            'migrations/', 'config/', 'docs/',
            'public/', 'templates/', 'translations/',
        ],
        'module_patterns' => [
            'src/Module/*',
            'apps/*/src/Module/*',
            'apps/*/src/**/Module/*',
        ],
        'thresholds' => [
            'class' => ['loc' => 300, 'wmc' => 50, 'max_cc' => 10],
            'module' => ['external_dependency_share' => 0.5, 'cycles' => 0],
        ],
    ],
];
```

Укажите реальные пути к конфигурациям Deptrac и PHPUnit. Проектный
`depfile.yaml`, созданный командой инициализации, импортирует общий конфиг
пакета, где уже зарегистрирован форматтер `metrics-json`. При полностью своей
конфигурации Deptrac зарегистрируйте форматтер вручную:

```yaml
services:
  - class: PrikotovCodingStandard\Deptrac\MetricsJsonOutputFormatter
    tags:
      - { name: output_formatter }
```

Сгенерированные файлы не должны попадать в репозиторий проекта:

```gitignore
/var/metrics/
```

### Команда Composer проекта

Scripts Composer установленных зависимостей не переносятся в корневой
`composer.json`. Добавьте команду именно в проект-потребитель:

```bash
composer config scripts.metrics vendor/bin/coding-standard-metrics
```

Эквивалентная запись в его `composer.json`:

```json
{
  "scripts": {
    "metrics": "vendor/bin/coding-standard-metrics"
  }
}
```

После настройки полный отчёт собирается из корня проекта:

```bash
composer metrics
```

Без Composer script доступна та же команда напрямую:

```bash
vendor/bin/coding-standard-metrics
```

Команда последовательно собирает структурные метрики, полный граф Deptrac,
размер кодовой базы, статистику тестов и покрытие, затем создаёт:

- `var/metrics/report.json` — машиночитаемый отчёт для разработчика и ИИ-агента;
- `var/metrics/index.html` — автономный HTML-дашборд для просмотра в браузере;
- зеркальные отчёты каталогов и PHP-файлов внутри `var/metrics/`.

### Какие классы считаются модулями

Структурные метрики читают только production-корни из Composer `autoload`.
`autoload-dev`, тесты и классы вне каталогов из `module_patterns` не входят в
метрики классов и модулей. Название приложения берётся из PSR-4-префикса:

- `Project\Common\Module\Billing` → `Common:Billing`;
- `Project\Web\Module\Billing` → `Web:Billing`;
- `Project\Api\v1\Module\Chat` → `Api/v1:Chat`.

Классы из `packages/*` и технические классы вне `Module/*` не считаются
модулями и не входят в class-level и module-level метрики.

### Ошибки запуска

- `scc is required` — установите `scc` и добавьте бинарник в `PATH`.
- `PCOV is required` — установите и включите PCOV для используемой версии PHP.
- `Deptrac is not installed` — добавьте `deptrac/deptrac` в `require-dev` проекта.
- `PHPUnit is not installed` — обеспечьте команду `vendor/bin/phpunit` и укажите
  конфигурацию в `metrics.phpunit_config`.
- `metrics-json` не найден — импортируйте общий `depfile.yaml` пакета или
  зарегистрируйте `MetricsJsonOutputFormatter` вручную.
- Ошибка тестов прерывает сбор покрытия и всего отчёта. Архитектурные нарушения
  Deptrac не прерывают сбор, если файл полного графа успешно сформирован.

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
| `coding-standard-metrics` | Собирает полный отчёт и HTML-дашборд подключаемого проекта |
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
