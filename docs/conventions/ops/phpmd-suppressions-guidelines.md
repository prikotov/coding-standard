---
name: PHPMD Suppressions
type: rule
description: Правила обоснованного подавления предупреждений PHP Mess Detector
---

# Обоснованные подавления PHPMD (PHPMD suppression guidelines)

**Обоснованное подавление PHPMD (PHPMD suppression)** — контролируемое и документированное исключение из правила анализатора, которое допускается только после проверки, что рефакторинг сейчас непропорционален риску или стоимости изменений.

Официальные источники:
- [PHPMD](https://phpmd.org/)
- [PHPMD: Suppressing Warnings](https://phpmd.org/documentation/suppress-warnings.html)

## Общие правила

- `@SuppressWarnings(PHPMD.*)` применяем только как последний шаг после попытки упростить код без ухудшения контракта.
- Suppression должен быть локальным: метод или конкретный класс, а не модуль или пакет.
- Рядом с suppression обязателен комментарий причины в терминах домена/контракта, а не только "чтобы пройти линтер".
- Если suppression временный, создаётся/обновляется отдельная `todo/TASK-*` задача с планом снятия.
- Запрещено маскировать реальные ошибки через оператор `@` без техдолг-задачи и плана устранения.
- Запрещено добавлять suppression в код, если проблему можно исправить безопасной декомпозицией без изменения внешнего поведения.
- Любой новый suppression проверяется в ревью по чек-листу в конце этого документа.

## Матрица решений по правилам

| Rule | Допустимо | Недопустимо | Обязательное действие |
| :--- | :--- | :--- | :--- |
| `StaticAccess` | Вызов чистых stateless helper-классов и vendor static factory/normalizer, если это системное ограничение | Статический runtime I/O, обход DI ради "тихого" прохождения PHPMD | Сначала проверить вариант с инстансным seam; если нужен static — зафиксировать обоснование и, при необходимости, добавить FQCN в `phpmd.xml` (`StaticAccess exceptions`) |
| `ErrorControlOperator` | Только узкий низкоуровневый probe-кейс, где нет безопасной альтернативы API и есть явная обработка ошибки | Маскирование ошибок в Application/Domain и в runtime I/O без обработки | Предпочитать явную обработку ошибок (`try/catch`, проверка результата, логирование), suppression считать временным техдолгом |
| `UnusedFormalParameter` | Параметр обязателен по контракту (`__invoke`, interface, message handler), но payload пустой | Неиспользуемые аргументы в приватных методах и внутренних сервисах | Подтвердить контрактную необходимость; где можно — удалить параметр/упростить сигнатуру |
| Complexity rules (`CyclomaticComplexity`, `NPathComplexity`, `ExcessiveMethodLength`, `ExcessiveClassLength`, `TooMany*`) | Временное suppression на orchestration-точках с высоким риском регрессии | Систематическое подавление без плана декомпозиции и тестового покрытия | Создать отдельную техдолг-задачу с hotspot-областью, целевыми метриками и проверками |

## Зависимости

- Конфигурация PHPMD для CI: [`phpmd.xml`](../examples/phpmd.xml)
- Baseline rollout: `phpmd.baseline.xml` (генерируется через `vendor/bin/phpmd --generate-baseline`)
- CI/check pipeline: [`Makefile`](../examples/Makefile) (`make check`, `make phpmd-fast`)
- Рабочий контекст задач: см. `todo/` для актуальных задач по PHPMD

## Расположение

Документ хранится в `docs/conventions/ops/`:

```
docs/conventions/ops/phpmd-suppressions-guidelines.md
```

Применяется для всех модулей в `src/Module/*` и общих компонентов в `src/Component/*`.

## Как используем

### 1. Blocking-контур (обязательный для merge)

- Источник правды: `phpmd.xml` + `phpmd.baseline.xml`.
- Проверка в CI: `make check` (включает `make phpmd-fast`).
- Правило gate:
  - не добавлять новый suppression без обоснования;
  - не увеличивать baseline без отдельной согласованной задачи.

### 2. Strict-контур (обязательный для управления долгом)

- Выполняется в рамках PHPMD-задач и архитектурного обзора, не блокирует merge напрямую.
- Минимальный набор метрик:
  - общее количество `@SuppressWarnings(PHPMD.*)` в `src/`;
  - количество записей в `phpmd.baseline.xml`;
  - hotspot-правила из задач текущей волны.
- Рекомендуемые команды фиксации среза:

```bash
vendor/bin/phpmd src text phpmd.xml --baseline-file phpmd.baseline.xml
rg -n '@SuppressWarnings\(PHPMD\.' src --glob '*.php' | wc -l
rg -n '<violation rule=' phpmd.baseline.xml | wc -l
```

### 3. Ratchet-последовательность и переходы

| Метрика | Ratchet-последовательность | Текущее blocking-значение (2026-02-26) | Следующий шаг |
| :--- | :--- | :--- | :--- |
| `CyclomaticComplexity reportLevel` | `50 -> 35 -> 25 -> 18 -> 12` | `25` | перейти к `18` после закрытия следующей волны hotspot-рефакторинга |
| `ExcessiveMethodLength minimum` | `300 -> 220 -> 160 -> 120 -> 80` | `80` | удерживать `80`, дальнейшее снижение только отдельным ADR/эпиком |

Переход на следующий ratchet-шаг разрешён только если одновременно выполнены условия:
- `make check` стабильно зелёный;
- strict-срез не показывает роста suppressions/baseline;
- закрыты hotspot-задачи текущего шага с тестовым подтверждением;
- нет роста архитектурного риска (DDD-границы и контракты сохранены).

## Пример

Реальный обоснованный `UnusedFormalParameter` в message handler (контракт `__invoke`, пустой query payload):

```php
/**
 * @psalm-suppress UnusedParam
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
public function __invoke(CheckPdfHealthQuery $query): PdfHealthDto
{
    // Query не содержит параметров, используется для type-safety
    $version = $this->pdfinfoComponent->getVersion();

    return new PdfHealthDto(
        isHealthy: true,
        version: $version,
        errorMessage: null,
    );
}
```

Реальный техдолг-кейс с `ErrorControlOperator`, который должен вытесняться явной обработкой ошибок:

```php
/**
 * @SuppressWarnings(PHPMD.ErrorControlOperator)
 */
private function isPortOpen(int $port): bool
{
    $socket = @fsockopen($this->host, $port, timeout: 1);

    if ($socket === false) {
        return false;
    }

    fclose($socket);

    return true;
}
```

Реальный допустимый `StaticAccess` для чистого helper-вызова:

```php
$chunkText = CleanOverlapStringHelper::clean($previousChunkText, $chunk->text);
```

## Чек-лист для ревью кода

- [ ] Для каждого нового suppression есть конкретная причина и границы применения.
- [ ] Причина suppression выражена через контракт/архитектурное ограничение, а не через удобство.
- [ ] Для временного suppression есть связанная `todo/TASK-*` с планом снятия.
- [ ] Нет новых suppressions на `ErrorControlOperator` без плана удаления.
- [ ] Изменение не ухудшает DDD-границы и покрыто тестами нужного уровня.
- [ ] Проверены оба контура: blocking (`make check`) и strict (срез метрик).
