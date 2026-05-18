---
type: feat
created: 2026-05-17
value: V3
complexity: C3
priority: P1
depends_on:
epic:
author: pi
assignee:
branch:
pr: https://github.com/prikotov/coding-standard/pull/43
status: todo
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

## План

1. Исследовать существующий `bin/validate-docs.php` и решить:
   - расширять его;
   - или добавить отдельный скрипт `bin/validate-md-links.php`.
2. Реализовать парсер Markdown-ссылок без тяжёлых зависимостей.
3. Исключать fenced code blocks перед поиском ссылок.
4. Реализовать резолв относительных путей.
5. Реализовать построение anchor index по заголовкам Markdown-файла.
6. Добавить понятный вывод ошибок:
   ```text
   docs/conventions/index.md:42 broken-link target not found: ./missing.md
   docs/conventions/foo.md:17 broken-anchor target exists but anchor not found: bar.md#section
   ```
7. Добавить composer script:
   ```json
   "validate-md-links": "php bin/validate-md-links.php"
   ```
8. Включить проверку в `composer check`.
9. Добавить PHPUnit-тесты или fixture-based тесты для валидатора.
10. Обновить документацию/README с командой запуска.

## Definition of Done

- [ ] `composer validate-md-links` падает на битой внутренней ссылке.
- [ ] `composer validate-md-links` падает на несуществующем anchor.
- [ ] Валидатор не проверяет внешние URL в MVP.
- [ ] Валидатор игнорирует ссылки внутри fenced code blocks.
- [ ] Валидатор корректно работает с русскими заголовками.
- [ ] Валидатор поддерживает reference-style links.
- [ ] Валидатор выдаёт файл, строку, тип ошибки и target.
- [ ] `composer check` включает проверку ссылок.
- [ ] Есть тесты/fixtures на happy path и broken links.

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
