# Стандарт кодирования и обеспечения качества для PHP-проектов

Главная цель стандарта кодирования — поддержание высокой скорости разработки и удобной поддержки кода в долгосрочной перспективе. Скорость достигается за счёт единообразия: разработчик и AI-агент сразу знают, где лежит DTO, как устроен хендлер, какие зависимости допустимы между слоями. Поддержка — за счёт архитектурных подходов, зафиксированных в конвенциях: изоляция слоёв, чистые доменные модели, строгие границы модулей.

AI-агенты склонны отклоняться от конвенций. Поэтому соблюдение конвенций проверяется автоматически: сниффы PHP CodeSniffer и правила Deptrac ловят нарушения до кодревью. 

---

## Вектор развития

Проект развивается не только как набор правил для разработчиков и ИИ-агентов, а как **детерминированная система управления качеством кодовой базы**.

Главная цель инструментального контура — дать ИИ-агенту **детерминированную обратную связь о качестве его решений**. Агент запускает проверки до и после изменения, видит нарушения конвенций и изменение метрик и исправляет код до ручного ревью. Обратную связь формируют утилиты, которые проверяют соблюдение конвенций и измеряют качество кодовой базы.

Целевая система состоит из трёх связанных уровней:

1. **Конвенции** фиксируют архитектурные и кодовые решения в форме, понятной человеку и ИИ-агенту: как разделять модули и слои, где размещать сущности и какие зависимости допустимы.
2. **Автоматические проверки** контролируют следование конвенциям. `PHP_CodeSniffer`, `PHPStan`, `Deptrac` и валидаторы документации выдают воспроизводимые нарушения с конкретным файлом и строкой.
3. **Метрики качества** измеряют размер, сложность, связность и динамику кодовой базы. Они дополняют обязательные правила и рекомендации числовыми ориентирами для оценки ценностей, закреплённых в конвенциях: читаемости, стабильности и умеренности в сложности.

Метрики планируются на нескольких уровнях:

- **метод и класс** — размер, сложность, количество методов и свойств, сцепление методов и зависимости между классами;
- **модуль** — распределение размера и сложности, внутренняя и внешняя связанность, циклические зависимости и размер публичного интерфейса;
- **проект** — общий размер кодовой базы, состав компонентов, сводные значения и изменение показателей во времени.

Тесты и покрытие учитываются на каждом уровне: для отдельных методов и классов, модулей и проекта в целом.

Расчёты должны быть детерминированными: формулы и схема машинного отчёта версионируются, одинаковый исходный код при одинаковых версиях инструментов даёт одинаковый результат. Сырые значения отделяются от списка найденных проблем (`findings`), а пороги для блокировки `CI` вводятся явно и только после проверки на реальных проектах. Единый непрозрачный «балл качества» не используется.

Эта цепочка прорабатывается в эпике [`EPIC-metrics-ai-maintainability`](todo/EPIC-metrics-ai-maintainability.todo.md). Исследование формул, готовых анализаторов, диагностических инструментов и варианта собственного сборщика сохранено в [отчёте о выборе инструментов метрик](docs/research/metrics-tools-evaluation.md).

---

## Конвенции

Документация описывает принципы, паттерны, слои, модули, тестирование и структуру Symfony-приложения. Служит справочником для команды и AI-агентов.

Полное содержание — в [индексе конвенций](docs/conventions/index.md).

Обоснования инструментальных решений хранятся отдельно от задач: [сравнение PHP-анализаторов метрик](docs/research/metrics-tools-evaluation.md).

---

## Автоматические проверки

Соблюдение конвенций проверяется через PHP CodeSniffer и Deptrac — без ручного кодревью структуры.

### Markdown-валидация

Проверка документации ведётся двумя инструментами:

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

## Установка

```bash
composer require --dev prikotov/coding-standard
```

Скопируйте конвенции и конфигурации в проект:

```bash
php vendor/bin/coding-standard-init --project-name=ProjectName
```

В состав пакета входят:

- **Сниффы** — PHP CodeSniffer-правила, работают сразу из `vendor/`
- **Deptrac-правила** — пользовательские правила для deptrac
- **PHPStan-правила** — пользовательские правила для phpstan
- **Конфигурации** — `depfile.yaml` для Deptrac, `phpcs.xml.dist` для `PHPCS`, `phpstan.neon.dist` для PHPStan
- **Шаблоны исключений** — типовые классы и интерфейсы, копируются с подстановкой namespace проекта
- **Конвенции** — документация, копируется командой `coding-standard-init`

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
