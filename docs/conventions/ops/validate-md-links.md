---
package: prikotov/coding-standard
name: Валидация внутренних ссылок в Markdown (validate-md-links)
type: rule
description: Настройка и использование валидатора внутренних ссылок в Markdown-файлах проекта
---

# Валидация внутренних ссылок в Markdown (`validate-md-links`)

**`validate-md-links`** — CLI-инструмент для проверки внутренних ссылок в Markdown-файлах. Находит битые относительные пути и несуществующие якоря до merge/release.

## Общие правила

- Валидатор проверяет только **внутренние** ссылки (относительные пути и якоря).
- Внешние URL (`https://`, `mailto:`), изображения и ссылки внутри `fenced code blocks` **игнорируются**.
- Валидатор поддерживает `inline`-ссылки, `reference-style`-ссылки и якоря (`anchors`).
- Генерация `slug` якоря совместима с GitHub: нижний регистр (`lower-case`), русские символы, дубликаты с суффиксами `-1`, `-2`.

## Что проверяется

### Типы ссылок

| Тип | Синтаксис | Проверяется |
|---|---|---|
| `Inline`-ссылка | `текст → path.md` | ✅ |
| Ссылка с якорем | `текст → path.md#section` | ✅ |
| Локальный якорь | `текст → #section` | ✅ |
| `reference-style` | `текст → [id] + определение [id]` | ✅ |
| Внешний URL | `https://...` | ❌ пропускается |
| Изображение | `!изображение` | ❌ пропускается |
| Ссылка в `code block` | внутри ``` или `` ` `` | ❌ пропускается |

### Типы ошибок

| Тип | Описание |
|---|---|
| `broken-link` | Целевой файл не найден |
| `broken-anchor` | Файл существует, но якорь не найден |
| `broken-ref` | `reference-style`-ссылка на неопределённый `[id]` |

## Установка

Инструмент входит в состав `prikotov/coding-standard`:

```bash
composer require --dev prikotov/coding-standard
```

Запуск:

```bash
php vendor/bin/validate-md-links
```

Или через composer-скрипт (если добавлен в `composer.json`):

```bash
composer validate-md-links
```

## Настройка

### Конфиг-файл `.md-links.php`

Создайте файл `.md-links.php` в корне проекта. Валидатор загружает его автоматически при запуске без аргументов.

```php
<?php

declare(strict_types=1);

return [
    // Файлы и директории для сканирования.
    'paths' => ['docs/', 'todo/', 'README.md', 'AGENTS.md'],

    // Фрагменты путей для исключения (substring match).
    'exclude' => [
        'docs/todo-md/templates/',
        'docs/api/generated/',
    ],
];
```

**Поля конфига:**

| Поле | Тип | По умолчанию | Описание |
|---|---|---|---|
| `paths` | `string[]` | `['docs/', 'README.md', 'AGENTS.md']` | Файлы и директории для сканирования |
| `exclude` | `string[]` | `[]` | Фрагменты путей для исключения (substring match) |
| `skip_dirs` | `string[]` | `['vendor/', '.git/', ...]` | Имена директорий, пропускаемых всегда |

### Аргументы командной строки

Аргументы **переопределяют** значения из конфиг-файла.

```
php vendor/bin/validate-md-links [options] [path...]

Options:
  --config=<file>     Путь к конфиг-файлу (по умолчанию: .md-links.php)
  --exclude=<pat>     Исключить пути, содержащие pat (можно указывать несколько раз)
  --no-fail           Выход с кодом 0, даже если есть ошибки
```

**Примеры:**

```bash
# Проверить конкретную директорию
php vendor/bin/validate-md-links docs/conventions/

# Исключить шаблоны
php vendor/bin/validate-md-links --exclude=docs/api/ --exclude=docs/drafts/

# Использовать кастомный конфиг
php vendor/bin/validate-md-links --config=build/md-links-config.php

# Проверить один файл
php vendor/bin/validate-md-links README.md
```

## Встраивание в проверки проекта

### Вариант 1: Composer-скрипт (рекомендуемый)

Добавьте в `composer.json` проекта:

```json
{
    "scripts": {
        "validate-md-links": "php vendor/bin/validate-md-links",
        "check": [
            "@test",
            "@validate-md-links",
            "phpcs src/"
        ]
    }
}
```

Запуск: `composer check`.

### Вариант 2: `Makefile`

```makefile
.PHONY: check
check: validate-md-links phpcs test

.PHONY: validate-md-links
validate-md-links:
	php vendor/bin/validate-md-links
```

Запуск: `make check`.

### Вариант 3: CI (GitHub Actions)

```yaml
- name: Validate markdown links
  run: php vendor/bin/validate-md-links
```

### Постепенное внедрение

Если в проекте много существующих битых ссылок, используйте `--no-fail` для переходного периода:

```json
{
    "scripts": {
        "validate-md-links": "php vendor/bin/validate-md-links --no-fail"
    }
}
```

Валидатор будет выводить ошибки, но не ломать CI. Когда ссылки исправлены — уберите `--no-fail`.

## Расположение

- Скрипт: `vendor/prikotov/coding-standard/bin/validate-md-links`
- Конфиг: `.md-links.php` в корне проекта

## Чек-лист для проведения ревью кода

- [ ] `.md-links.php` создан в корне проекта с актуальными `paths` и `exclude`
- [ ] `composer validate-md-links` добавлен в `composer check` или `make check`
- [ ] CI запускает валидатор
- [ ] Все внутренние ссылки проходят проверку
