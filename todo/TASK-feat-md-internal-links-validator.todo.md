---
type: feat
created: 2026-05-17
value: V3
complexity: C3
priority: P1
depends_on:
epic:
author: pi
assignee: pi
branch: task/feat-md-internal-links-validator
pr: https://github.com/prikotov/coding-standard/pull/44
status: review
---

# TASK-feat-md-internal-links-validator: Markdown Internal Links Validator

## Цель

Добавить в `coding-standard` валидатор внутренних ссылок в Markdown-документации, чтобы CI и локальная проверка ловили битые относительные ссылки до merge/release.

## Контекст

В `coding-standard` уже есть `composer validate-docs`, который проверяет структуру документации `docs/conventions/`. При росте документации и копировании конвенций в проекты-потребители часто ломаются относительные ссылки: переименовали файл, перенесли раздел, изменили заголовок-якорь, забыли обновить индекс.

Это задача уровня code/documentation quality, поэтому валидатор должен жить в `coding-standard`. `git-workflow` может только рекомендовать запуск такой проверки перед PR, но не должен владеть самим инструментом.

## Что проверять

### Область проверки

- Все Markdown-файлы в целевых директориях, минимум:
  - `docs/**/*.md`
  - `README.md`
  - опционально `AGENTS.md`
- Возможность передать путь/список путей аргументами.
- По умолчанию не сканировать:
  - `vendor/`
  - `.git/`
  - `var/`, `tmp/`, `cache/`

### Типы ссылок

Проверять внутренние Markdown-ссылки:

- `[text](relative/path.md)`
- `[text](./file.md)`
- `[text](../folder/file.md)`
- `[text](file.md#anchor)`
- `[text](#local-anchor)`
- reference-style links:
  - `[text][id]`
  - `[id]: relative/path.md#anchor`

Не проверять в MVP:

- внешние URL: `https://...`, `http://...`
- `mailto:`
- изображения `![alt](...)`, если решим не включать их в первый релиз
- ссылки внутри fenced code blocks

### Проверка файла

- Относительный путь резолвится от файла, где находится ссылка.
- Если ссылка указывает на директорию — договориться о поведении:
  - либо ошибка;
  - либо искать `index.md` внутри директории.
- URL-encoded символы должны корректно декодироваться.
- Query string (`?x=y`) для локальных ссылок либо игнорируется, либо запрещается явно.

### Проверка якоря

- Для `#anchor` проверить существование соответствующего Markdown-заголовка.
- Генерация slug должна быть совместима с GitHub Markdown насколько возможно:
  - lower-case;
  - пробелы → `-`;
  - удаление пунктуации;
  - дубликаты получают суффиксы `-1`, `-2`.
- Поддержать русские заголовки.
- Front matter не должен ломать парсинг заголовков.

## План реализации

Решение: **отдельный скрипт** `bin/validate-md-links.php`. `validate-docs.php` уже несёт 4 проверки для `docs/conventions/`, а валидатор ссылок — более通用 инструмент для любых `.md` файлов проекта.

1. [x] Создать `bin/validate-md-links.php` — standalone скрипт без зависимостей
2. [x] Реализовать парсер: inline-ссылки + reference-style links
3. [x] Исключать fenced code blocks (``` и ~~~) перед парсингом
4. [x] Резолв относительных путей от файла-источника
5. [x] Anchor index: GitHub-совместимый slug, русские заголовки, дубликаты с суффиксами
6. [x] Вывод ошибок: `file:line error-type: target`
7. [x] Composer script `validate-md-links`
8. [x] Включить в `composer check`
9. [x] Fixture-тесты: happy path + broken links
10. [x] Обновить README

## Definition of Done

- [x] `composer validate-md-links` падает на битой внутренней ссылке.
- [x] `composer validate-md-links` падает на несуществующем anchor.
- [x] Валидатор не проверяет внешние URL в MVP.
- [x] Валидатор игнорирует ссылки внутри fenced code blocks.
- [x] Валидатор корректно работает с русскими заголовками.
- [x] Валидатор поддерживает reference-style links.
- [x] Валидатор выдаёт файл, строку, тип ошибки и target.
- [x] `composer check` включает проверку ссылок.
- [x] Есть тесты/fixtures на happy path и broken links (17 тестов).

## Verification

```bash
composer validate-md-links
composer check
```

Для ручной проверки создать fixture с:

- валидной ссылкой на соседний `.md`;
- битой ссылкой на отсутствующий файл;
- валидным anchor;
- битым anchor;
- ссылкой в code block, которая должна игнорироваться.

## Риски

- Markdown edge cases. Mitigation: MVP покрывает обычные ссылки, сложные случаи документируются как ограничения.
- Отличия slug алгоритма GitHub от локального. Mitigation: тесты на русские и повторяющиеся заголовки.
- False positive для нестандартных ссылок. Mitigation: allowlist/ignore comments можно добавить отдельной задачей.
- Замедление `composer check`. Mitigation: проверка только `.md`, без сети и внешних URL.

## Зависимости

- `bin/validate-docs.php` — текущая валидация документации.
- `docs/conventions/` — основной набор документов.
- `composer check` — общий quality gate.

## Change History

| Дата | Автор | Изменение |
| :--- | :--- | :--- |
| 2026-05-17 | AI-ассистент | Создание задачи |
