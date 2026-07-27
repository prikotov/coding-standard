---
name: Валидация англицизмов в Markdown (validate-language)
type: rule
lang: ru
description: Настройка и использование валидатора английских фраз в русскоязычном тексте Markdown-файлов
---

# Валидация англицизмов в Markdown (validate-language)

**`validate-language`** — CLI-инструмент для расчёта доли англицизмов в русскоязычном тексте Markdown/text-файлов. Находит англицизмы (например «persisted rows», «read-only facts», одиночное «allowlist») до merge/release, не требуя ручного языкового ревью.

## Общие правила

- **`ratio`** = (латинские слова вне allowlist) / (все слова). Англицизм — любое латинское слово вне allowlist: одиночное (например «allowlist») или в обороте (например «persisted rows»). Файлы, где `ratio` превышает порог, помечаются warning. Порог по умолчанию — **2%** (настраивается через `--max-ratio` или ключ `max_ratio` конфига).
- Латиница в inline code (`` `ratio` ``), code blocks, URL, namespaces и т.п. исключается экстрактором; разрешённые термины — allowlist'ом. Они не раздувают `ratio`.
- Латиница в кавычках «термин» и скобках `(term)` исключается экстрактором — это цитирование терминов и переводы, а не англицизмы в тексте.
- Режим по умолчанию — **warning**: выводит файлы с превышением, но завершается кодом 0. Флаг `--strict` — завершать кодом 1.

## Что проверяется

### Язык документа

Проверяются документы на русском языке. Документ на другом языке пропускается.

Признак языка указывается одним из способов:

- front matter: `lang: ru`;
- имя файла: `glossary.ru.md` — код языка из 2 букв перед `.md`.

Если язык не указан ни в front matter, ни в имени файла — документ считается русским и проверяется. Если указан в обоих местах и значения различаются — это ошибка конфигурации, валидатор сообщает о конфликте маркеров.

### Исключения из анализа

Из подсчёта исключаются технические фрагменты (они не относятся к тексту):

| Фрагмент | Пример |
|---|---|
| YAML front matter | `---\nname: ...\n---` |
| Fenced code blocks | ` ``` ... ``` ` |
| Inline code | `` `code` `` |
| URL | `https://example.com` |
| Namespaces | `\App\Module\Foo` |
| References | `#65`, `@user`, `gh-123` |
| Task IDs | `TASK-feat-...` |
| Имена файлов | `AGENTS.md`, `config.php` |
| UPPER_SNAKE идентификаторы | `AGENTS_TASK_WRITING_GUIDE` |
| Плейсхолдеры | `{ProjectName}` |
| Латиница в скобках (перевод термина) | `(Command Handler)` |
| Латиница в кавычках (цитирование термина) | «allowlist», "persisted rows" |

### Allowlist технических терминов

Allowlist (case-insensitive) задаётся в конфиге `.coding-standard.php` (ключ `language.allowlist`) — захардкоженного списка в коде нет, проект сам решает что считать термином, а не англицизмом. `coding-standard-init` записывает начальный набор — имена собственные и аббревиатуры (`Symfony`, `Doctrine`, `PHP`, `SQL`, `API`, `DTO`, ...).

### Словарь стандартных переводов (dictionary)

Пока `allowlist` отвечает на «что НЕ трогать» (термины, жаргон, имена собственные), `dictionary` (ключ `language.dictionary`) отвечает на «как переводить» — карта «английское слово/фраза → стандартный русский перевод» (`case-insensitive`).

`validate-language` рядом с найденным англицизмом, для которого есть перевод в `dictionary`, выводит подсказку: в текстовом режиме — строка `Hints:`, в `--json` — поле `suggestions` со списком «англицизм → перевод» по файлу. На `ratio` подсказки не влияют — это диагностика, а не автоматическая замена.

- **Однословные ключи** — подсказка, только если слово встретилось как англицизм (латиница вне `allowlist`). Слово в `allowlist` подсказку не получает: оно термин, не англицизм.
- **Многословные фразы** — совпадение по последовательности слов; перенос строки и отступы между словами допускаются.
- **Стартовый набор** — `coding-standard-init` записывает консервативный `dictionary` (только устоявшиеся переводы: `hook`, `execution`, `path`, `resume`, `god object`, `parallel`, `design`, `action`); потребитель дополняет под проект.

Связка: `allowlist` = «оставить как есть», `dictionary` = «переводить так». Вместе они задают полную политику отношения к английскому в русскоязычной документации.

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

        // Разрешённые термины (дополняйте под проект).
        'allowlist' => ['Panther', 'Twig', 'AssetMapper'],

        // Стандартные переводы англицизмов (дополняйте под проект; на ratio не влияет).
        'dictionary' => [
            'hook' => 'хук',
            'execution' => 'выполнение',
            'god object' => 'божественный объект',
        ],
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
- [ ] Слова, требующие единообразного перевода, добавлены в `language.dictionary` секции `language` конфига `.coding-standard.php`; подсказки `validate-language` учтены при чистке текста.
- [ ] Намеренно англоязычные справочники исключены через `exclude`.
- [ ] Перед включением `--strict` в CI warning-режим дал 0 реальных англицизмов.
