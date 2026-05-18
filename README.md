# Стандарт кодирования для AI-агентов на PHP-проектах

Главная цель стандарта кодирования — поддержание высокой скорости разработки и удобной поддержки кода в долгосрочной перспективе. Скорость достигается за счёт единообразия: разработчик и AI-агент сразу знают, где лежит DTO, как устроен хендлер, какие зависимости допустимы между слоями. Поддержка — за счёт архитектурных подходов, зафиксированных в конвенциях: изоляция слоёв, чистые доменные модели, строгие границы модулей.

AI-агенты склонны отклоняться от конвенций. Поэтому соблюдение конвенций проверяется автоматически: сниффы PHP CodeSniffer и правила Deptrac ловят нарушения до кодревью. 

---

## Конвенции

Документация описывает принципы, паттерны, слои, модули, тестирование и структуру Symfony-приложения. Служит справочником для команды и AI-агентов.

Полное содержание: [docs/conventions/index.md](docs/conventions/index.md).

---

## Автоматические проверки

Соблюдение конвенций проверяется через PHP CodeSniffer и Deptrac — без ручного кодревью структуры.

### Markdown-валидация

| Команда | Что проверяет |
|---|---|
| `composer validate-docs` | Конвенции документации в `docs/conventions/` — front matter, kebab-case, обязательные секции, ссылки внутри каталога |
| `composer validate-md-links` | Ссылки между Markdown-файлами всего проекта (пути и якоря). Область проверки настраивается через `.md-links.php` |

Настройка и интеграция: [Валидация внутренних ссылок](docs/conventions/ops/validate-md-links.md).

### PHP CodeSniffer-сниффы

| Снифф | Что проверяет |
|---|---|
| `DtoStructureSniff` | DTO — `final readonly`, только promoted-параметры в конструкторе, без методов и свойств |
| `EnumStructureSniff` | Enum — чистый (без методов, констант, трейтов), case'ы в camelCase |
| `ValueObjectStructureSniff` | Value Object — `final readonly`, неизменяемый, приватный конструктор, статические фабрики |
| `CommandQueryStructureSniff` | Command/Query — конструктор только с promoted-параметрами, без свойств и методов |
| `CommandHandlerStructureSniff` | CommandHandler — только `__invoke`, без публичных свойств |
| `QueryHandlerReturnTypeSniff` | QueryHandler — должен возвращать `Result` или `ResultDto` |
| `CommandHandlerReturnTypeSniff` | CommandHandler — должен возвращать `void` или `Result` |
| `UseCaseNamingSniff` | UseCase — обязательный суффикс; имя файла и неймспейс совпадают с путём |
| `GlobalFunctionCallStyleSniff` | Глобальные функции вызываются без обратного слеша и без `use function` |

### Deptrac-правила

| Правило | Что проверяет |
|---|---|
| `ServiceContractDependencyRule` | Infrastructure зависит только от Domain-интерфейсов, не от конкретных классов |
| `CrossModuleDomainRule` | Домен одного модуля не зависит от домена другого — только через Application DTO |

Готовый `depfile.yaml` с правилами для DDD-слоёв и модульных границ: [`config/deptrac/`](config/deptrac/). Копируется в проект через `coding-standard-init` или вручную.

Примеры конфигураций: [`docs/conventions/examples/`](docs/conventions/examples/)

| Файл | Назначение |
|---|---|
| `phpcs.xml.dist` | PHP CodeSniffer |
| `phpunit.xml.dist` | PHPUnit |
| `phpmd.xml` | PHPMD |
| `psalm.xml` | Psalm |
| `Makefile` | Команды проверки (`make check`) |

---

## Установка

```bash
composer require --dev prikotov/coding-standard
```

В состав пакета входят:

- **Сниффы** — PHP CodeSniffer-правила, работают сразу из `vendor/`
- **Deptrac-правила** — пользовательские правила для deptrac
- **Конфигурации** — `depfile.yaml` для Deptrac, `phpcs.xml.dist` для PHPCS
- **Конвенции** — документация, копируется командой `coding-standard-init`

### Подключение PHPCS

```xml
<config name="installed_paths" value="vendor/prikotov/coding-standard"/>
<rule ref="PrikotovCodingStandard"/>
```

### Копирование конвенций в проект

```bash
php vendor/bin/coding-standard-init
```

По умолчанию существующие файлы не перезаписываются. Флаг `--force` включает перезапись.

```bash
php vendor/bin/coding-standard-init /path/to/project --docs-path=docs/ddd --deptrac-path=config/depfile.yaml --force
```

---

## License

[MIT](LICENSE)
