---
name: Валидация англицизмов в Markdown (validate-language)
type: rule
lang: ru
description: Настройка и использование валидатора английских фраз в русскоязычном тексте Markdown-файлов
---

# Валидация англицизмов в Markdown (validate-language)

**`validate-language`** — CLI-инструмент для поиска английских фраз в русскоязычном тексте Markdown/text-файлов. Находит англицизмы (например «persisted rows», «read-only facts») до `merge`/`release`, не требуя ручного языкового ревью.

## Общие правила

- Валидатор ищет **английские фразы в русском тексте** — пробежки из ≥2 латинских слов в строках, где есть и кириллица, и латиница. Это точный сигнал англицизма.
- Техническая терминология в отдельных списках и англоязычные справочники целиком **не считаются** англицизмами — нет смешения с русским текстом.
- **`ratio`** = (латинские слова вне `allowlist`) / (все слова). Одиночные англицизмы (`allowlist`) учитываются наравне с оборотами (`persisted rows`). Файлы, где `ratio` превышает порог, помечаются `warning`. Порог по умолчанию — **2%** (настраивается через `--max-ratio` или ключ `max_ratio` конфига).
- Термины в inline code (`` `ratio` ``) исключаются экстрактором, а разрешённые термины — `allowlist`'ом; они не раздувают `ratio`.
- Режим по умолчанию — **warning**: выводит файлы с превышением, но завершается кодом 0. Флаг `--strict` — завершать кодом 1.

## Что проверяется

### Язык документа

Проверяются только документы на русском языке (`ru`). Документ на другом языке явно помечается и пропускается — это убирает ложные срабатывания на английских справочниках и переводах.

Признак языка указывается одним из способов (приоритет — front matter):

| Способ | Русский (`ru`) — проверяется | Другой язык — пропускается |
|---|---|---|
| Front matter | `lang: ru` *(или без поля)* | `lang: en` |
| Имя файла | `dto.md` *(без суффикса языка)* | `glossary.en.md` |

Формат имени файла: `name.<lang>.md`, где `<lang>` — 2-буквенный код языка (опционально с регионом: `glossary.en-US.md`).

Если язык не указан ни в front matter, ни в имени файла — документ считается русским и проверяется.

### Исключения из анализа

Из подсчёта исключаются технические фрагменты (они не относятся к тексту):

| Фрагмент | Пример | Обработка |
|---|---|---|
| YAML front matter | `---\nname: ...\n---` | удаляется |
| Заголовки (ATX) | `## Section` | удаляется (структура, не текст) |
| Fenced code blocks | ` ``` ... ``` ` | удаляется |
| Inline code | `` `code` `` | удаляется |
| URL | `https://example.com` | удаляется |
| `Namespaces` | `\App\Module\Foo` | удаляется |
| `References` | `#65`, `@user`, `gh-123` | удаляется |
| `Task IDs` | `TASK-feat-...` | удаляется |
| Имена файлов | `AGENTS.md`, `config.php` | удаляется |
| UPPER_SNAKE идентификаторы | `AGENTS_TASK_WRITING_GUIDE` | удаляется |
| Плейсхолдеры | `{ProjectName}` | удаляется |
| Латиница в скобках | `(Command Handler)` | удаляется (перевод термина) |

### Allowlist технических терминов

Базовый `allowlist` (case-insensitive) включает имена собственные, аббревиатуры, названия DDD-слоёв/паттернов и базовую терминологию Markdown: `Symfony`, `Doctrine`, `PHP`, `SQL`, `API`, `DTO`, `Command`, `Query`, `Domain`, `Application`, `Service`, `Repository`, `Entity`, `VO`, `front`, `matter`, `code`, `block` и т.п. Слова из `allowlist` не считаются англицизмами.

Расширение `allowlist` под проект — через конфиг (ключ `allowlist`).

## Использование

```bash
# Проверка дефолтных путей (docs/, todo/, README.md, AGENTS.md).
vendor/bin/validate-language

# Указанные пути.
vendor/bin/validate-language docs/conventions src/README.md

# Жёсткий режим — завершать кодом 1 при превышении порога.
vendor/bin/validate-language --strict

# Своя максимально допустимая доля (по умолчанию 0.02 = 2%).
vendor/bin/validate-language --max-ratio=0.05

# Добавить термин в allowlist (повторяемый).
vendor/bin/validate-language --allow=Panther --allow=Twig

# Исключить пути по фрагменту.
vendor/bin/validate-language --exclude=docs/todo-md/reference

# Машиночитаемый вывод.
vendor/bin/validate-language --json
```

## Настройка

Конфигурация хранится в `.coding-standard.php` (там же, где `docs_path`) под ключом `language`:

```php
<?php

declare(strict_types=1);

return [
    'docs_path' => 'docs/conventions',

    // Конфигурация validate-language.
    'language' => [
        // Файлы/директории для сканирования (по умолчанию docs/, todo/, README.md, AGENTS.md).
        'paths' => ['docs/', 'todo/', 'README.md'],

        // Фрагменты путей для исключения.
        'exclude' => ['docs/todo-md/reference'],

        // Максимально допустимая доля слов в английских фразах (по умолчанию 0.02 = 2%).
        'max_ratio' => 0.05,

        // Дополнительные разрешённые термины (базовый allowlist расширяется, не заменяется).
        'allowlist' => ['Panther', 'Twig', 'AssetMapper'],
    ],
];
```

## Встраивание в проверки проекта

В `composer.json` проекта-потребителя:

```json
"scripts": {
    "docs:validate-language": "validate-language --strict"
}
```

Или в составе скрипта `docs:validate`:

```json
"scripts": {
    "docs:validate": [
        "@validate-docs",
        "@validate-md-links",
        "validate-language"
    ]
}
```

На этапе внедрения рекомендуется предупреждения без `--strict` — после чистки текстов включить жёсткий режим.

## Расположение

- Скрипт: `vendor/prikotov/coding-standard/bin/validate-language`
- Конфиг: секция `language` в `.coding-standard.php` в корне проекта

## Чек-лист для проведения ревью кода

- [ ] Английские фразы в русском тексте вынесены в переводы (русский термин, английский в скобках) или заменены русскими.
- [ ] Технические термины, используемые как жаргон, добавлены в `allowlist` секции `language` конфига `.coding-standard.php`.
- [ ] Намеренно англоязычные справочники исключены через `exclude`.
- [ ] Перед включением `--strict` в CI warning-режим дал 0 реальных англицизмов.
