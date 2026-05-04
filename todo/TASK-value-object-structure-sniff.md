---
type: feat
created: 2026-05-04
priority: P2
status: todo
---

# ValueObject Structure Sniff — PHPCS-проверки структуры VO

## Цель

Создать PHPCS-снифф `ValueObjectStructureSniff` — аналог `DtoStructureSniff`, но для ValueObject.
Проверять, что классы с суффиксом `Vo` в namespace `Domain\ValueObject\*` соответствуют конвенции.

## Контекст

Уже есть сниффы для DTO (`DtoStructureSniff`) и Enum (`EnumStructureSniff`).
ValueObject в конвенциях описан (`docs/conventions/core-patterns/value-object.md`), но автоматических проверок нет.

## Что проверять

### Структура класса

- `final readonly class` — обязателен.
- Конструктор с promoted `readonly`-свойствами.
- Конструктор не содержит исполняемого кода (пустое тело).
- Нет `const`, нет `use` (traits).

### Методы — разрешены только:

- Геттеры (`get*()`, `is*()`, `has*()`, `to*()`).
- `__toString()`.
- Методы-предикаты, возвращающие `bool` (например, `equals()`, `isEmpty()`).
- Статические фабричные методы (`from*()`, `create()`).

Запрещены:
- Методы с side-effect (возвращающие `void`, мутирующие состояние).
- `__set()`, `__unset()`, `__clone()`, `__sleep()`, `__wakeup()`.

### Namespace

- Класс должен находиться в `Domain\ValueObject\` внутри модуля.
- Если класс назван `*Vo`, но не лежит в `Domain\ValueObject\` — предупреждение.

### Свойства

- Только `readonly` (promoted или явные).
- Типы — primitives (`int`, `string`, `float`, `bool`, `array`, `\DateTimeImmutable`, `\UnitEnum`)
  или другие VO / Enum из того же модуля.
- Запрещено использовать Entity, Repository, Service в качестве типа свойства.

## План

1. Создать `src/Sniffs/Structure/ValueObjectStructureSniff.php`.
2. Создать тесты `tests/Sniffs/Structure/ValueObjectStructureSniffTest.php`.
3. Зарегистрировать в `ruleset.xml`.
4. Обновить конвенцию `docs/conventions/core-patterns/value-object.md` — добавить ссылку на снифф.

## Зависимости

- Конвенция: `docs/conventions/core-patterns/value-object.md`
- Аналог: `src/Sniffs/Structure/DtoStructureSniff.php`
