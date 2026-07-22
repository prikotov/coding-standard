---
name: Валидация англицизмов в Markdown (validate-language)
type: rule
description: Настройка и использование валидатора английских фраз в русскоязычной прозе Markdown-файлов
---

# Валидация англицизмов в Markdown (validate-language)

**`validate-language`** — CLI-инструмент для поиска английских фраз в русскоязычной прозе Markdown/text-файлов. Находит англицизмы (например «persisted rows», «read-only facts») до merge/release, не требуя ручного языкового ревью.

## Общие правила

- Валидатор ищет **английские фразы в русской прозе** — пробежки из ≥2 латинских слов в строках, где есть и кириллица, и латиница. Это точный сигнал англицизма.
- Техническая терминология в отдельных списках и англоязычные справочники целиком **не считаются** англицизмами — нет смешения с русским текстом.
- Одиночные английские слова вне allowlist учитываются в метрике `ratio`, но не триггерят warning (нужна фраза из ≥2 слов).
- Режим по умолчанию — **warning**: выводит найденные англицизмы, но завершается кодом 0. Флаг `--strict` — завершать кодом 1.

## Что проверяется

### Исключения из анализа

Из подсчёта исключаются технические фрагменты (они не относятся к прозе):

| Фрагмент | Пример | Обработка |
|---|---|---|
| YAML front matter | `---\nname: ...\n---` | удаляется |
| Заголовки (ATX) | `## Section` | удаляется (структура, не проза) |
| Fenced code blocks | ` ``` ... ``` ` | удаляется |
| Inline code | `` `code` `` | удаляется |
| URL | `https://example.com` | удаляется |
| Namespaces | `\App\Module\Foo` | удаляется |
| References | `#65`, `@user`, `gh-123` | удаляется |
| Task IDs | `TASK-feat-...` | удаляется |
| Имена файлов | `AGENTS.md`, `config.php` | удаляется |
| UPPER_SNAKE идентификаторы | `AGENTS_TASK_WRITING_GUIDE` | удаляется |
| Плейсхолдеры | `{ProjectName}` | удаляется |
| Латиница в скобках | `(Command Handler)` | удаляется (перевод термина) |

### Allowlist технических терминов

Базовый allowlist (case-insensitive) включает имена собственные, аббревиатуры и названия DDD-слоёв/паттернов: `Symfony`, `Doctrine`, `PHP`, `SQL`, `API`, `DTO`, `Command`, `Query`, `Domain`, `Application`, `Service`, `Repository`, `Entity`, `VO` и т.п. Слова из allowlist не считаются англицизмами.

Расширение allowlist под проект — через конфиг (ключ `allowlist`).

## Использование

```bash
# Проверка дефолтных путей (docs/, todo/, README.md, AGENTS.md).
vendor/bin/validate-language

# Указанные пути.
vendor/bin/validate-language docs/conventions src/README.md

# Жёсткий режим — завершать кодом 1 при наличии англицизмов.
vendor/bin/validate-language --strict

# Добавить термин в allowlist (повторяемый).
vendor/bin/validate-language --allow=Panther --allow=Twig

# Исключить пути по фрагменту.
vendor/bin/validate-language --exclude=docs/todo-md/reference

# Машиночитаемый вывод.
vendor/bin/validate-language --json
```

## Настройка

Конфиг `.language-lint.php` в корне проекта возвращает массив:

```php
<?php

declare(strict_types=1);

return [
    // Файлы/директории для сканирования (по умолчанию docs/, todo/, README.md, AGENTS.md).
    'paths' => ['docs/', 'todo/', 'README.md'],

    // Фрагменты путей для исключения.
    'exclude' => ['docs/todo-md/reference'],

    // Дополнительные разрешённые термины (базовый allowlist расширяется, не заменяется).
    'allowlist' => ['Panther', 'Twig', 'AssetMapper'],
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
- Конфиг: `.language-lint.php` в корне проекта

## Чек-лист для проведения ревью кода

- [ ] Английские фразы в русской прозе вынесены в переводы (русский термин, английский в скобках) или заменены русскими.
- [ ] Технические термины, используемые как жаргон, добавлены в `allowlist` конфига `.language-lint.php`.
- [ ] Намеренно англоязычные справочники исключены через `exclude`.
- [ ] Перед включением `--strict` в CI warning-режим дал 0 реальных англицизмов.
