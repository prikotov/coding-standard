---
name: Value Object
type: rule
description: Правила создания и использования объектов-значений
---

# Объект-Значение (Value Object)

**Объект-Значение (Value Object, VO)** — неизменяемый объект, определяемый своими значениями, инкапсулирующий инварианты, валидацию и простые операции над своими значениями..

Источник: [Martin Fowler - ValueObject](https://martinfowler.com/bliki/ValueObject.html)

## Общие правила

- Класс `final readonly`, свойства приватные и строго типизированные.
- Нет идентификатора. Равенство определяется по значениям всех атрибутов. При необходимости, если VO участвует в сравнениях или бизнес-условиях, добавляйте явный метод `equals(self $other): bool`.
- Инициализация через конструктор или статические фабрики; сеттеры отсутствуют.
- Если требуется несколько форматов создания или строгий контроль инвариантов, используйте статические фабричные методы с префиксом `createFrom*()` и объявляйте конструктор `private`.
- Валидируйте и нормализуйте входные данные в конструкторе или фабричном методе. При нарушении инвариантов или некорректных входных данных выбрасывайте `InvalidArgumentException`.
- Запрещены побочные эффекты и I/O; допускаются только чистые операции над собственными значениями.
- Допустимы только простые и детерминированные преобразования и нормализация (например, тримминг, `mb_strtolower` для e-mail).
- `to*()` — конвертер в другой тип (`toString(): string`, `toArray(): array`, `toInt(): int`). `to*()` не должен возвращать `self`/`static`.
- `with*()` не используется. Если нужно получить VO с изменённым значением — явно создаём `new SomeVo(...)` с нужными параметрами. VO не должен знать, как «модифицировать» себя.
- Исключение: операции над значениями, где VO знает как вычислить результат (например, `add(Money $other): self`). Это не модификация поля, а вычисление нового значения.
- Именование: постфикс `Vo` (например, EmailVo, MoneyVo) для явного отделения от Entity, DTO и других типов классов.
- Сериализация при необходимости — через `JsonSerializable`.

## Зависимости

- Разрешены примитивы, `DateTimeImmutable`, `Uuid`, `Enum` и другие VO того же слоя.
- Запрещены Infrastructure, ORM/DB, внешние SDK, контейнер DI и любое глобальное состояние.

## Расположение

- VO [слоя Domain](../layers/domain.md):

```
{ProjectName}\Common\Module\{ModuleName}\Domain\ValueObject\{Name}Vo
```

- Встраиваемые VO для ORM (Embeddable), применяемые внутри моделей сущностей:

```
{ProjectName}\Common\Module\{ModuleName}\Domain\Entity\ValueObject\{Name}Vo
```

- VO [слоя Application](../layers/application.md) (для представления сложных типов данных и инкапсуляции форматов представления, без бизнес-логики домена):

```
{ProjectName}\Common\Module\{ModuleName}\Application\ValueObject\{Name}Vo
{ProjectName}\Common\Module\{ModuleName}\Application\Dto\{Name}DtoVo
```

- VO [слоя Presentation](../layers/presentation.md) (если необходимы объекты представления):

```
{ProjectName}\\Web\\Module\\{ModuleName}\\...\\ValueObject\\{Name}Vo
Api\\v1\\Module\\{ModuleName}\\...\\ValueObject\\{Name}Vo
{ProjectName}\\Console\\Module\\{ModuleName}\\...\\ValueObject\\{Name}Vo
```

- VO [слоя Infrastructure](../layers/infrastructure.md) (служебные VO для компонентов/адаптеров):

```
Common\\Module\\{ModuleName}\\Infrastructure\\Component\\{Component}\\Vo\\{Name}Vo
Common\\Module\\{ModuleName}\\Infrastructure\\...\\ValueObject\\{Name}Vo
```

### Группировка сложных VO

Если VO состоит из нескольких классов (вспомогательные типы/подтипы), группируйте их по смыслу в подкаталоге:

```
{ProjectName}\Common\Module\{ModuleName}\Domain\ValueObject\{GroupName}\{Name}Vo
{ProjectName}\Common\Module\{ModuleName}\Domain\Entity\ValueObject\{GroupName}\{Name}Vo
```

## Как используем

- Используем как любой другой тип данных в пределах соответствующего слоя.
- Соблюдаем изоляцию слоёв: Value Object используются только в пределах своего слоя.
- [Сущности (Entity)](../layers/domain/entity.md) хранят VO в полях; изменения состояния — через методы сущности, создающие новые VO при необходимости.
- Для персистентности (только для Embeddable VO): составные VO объявляются как `#[Embeddable]` внутри ORM-моделей.

### Когда выбирать VO, а не DTO

- В `Domain` выбирайте `VO`, если объект выражает доменное понятие, содержит инварианты и должен быть безопасно переиспользуем внутри бизнес-логики.
- `DTO` используйте как транспорт данных на границах слоёв/модулей (Application, Integration, Presentation), когда инварианты домена не требуются.
- Иммутабельность сама по себе не делает объект `VO`: ключевой критерий — наличие доменного смысла и правил.

## Пример

```php
<?php

declare(strict_types=1);

namespace ProjectName\Common\Module\User\Domain\ValueObject;

use InvalidArgumentException;

final readonly class EmailVo
{
    private function __construct(
        private string $value,
    ) {}

    public static function createFromEmail(string $email): self
    {
        $email = mb_strtolower(trim($email));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email format.');
        }
        return new self($email);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
```

## Автоматические проверки (PHPCS)

Структура ValueObject автоматически проверяется сниффом `ValueObjectStructureSniff` (пакет `prikotov/coding-standard`).

Снифф проверяет:

- Класс объявлен как `final readonly`.
- Нет `const` и `use` (traits).
- Статические методы разрешены только `createFrom*()`. При наличии static-фабрики конструктор должен быть `private`.
- Нестатические методы: геттеры (`get*`, `is*`, `has*`, `to*`), предикаты с возвратом `bool`, `__toString`.
- `with*()` запрещены — используйте явное создание `new SomeVo(...)`.
- `to*()` — конвертер в другой тип. Возврат `self`/`static` из `to*()` запрещён.
- Запрещены магические методы `__set`, `__unset`, `__clone`, `__sleep`, `__wakeup`.
- Запрещён тип возврата `void`.
- Свойства — только `readonly`; запрещены типы, оканчивающиеся на `Entity`, `Repository`, `Service`.
- Предупреждение, если класс `*Vo` не находится в namespace, содержащем `ValueObject` или `Vo`.

## Чек-лист для проведения ревью кода

- [ ] Класс `final readonly`, без сеттеров и побочных эффектов.
- [ ] Именование с постфиксом `Vo`; корректное расположение в соответствующем слое.
- [ ] Валидация и нормализация данных выполняются при создании; при ошибке — `InvalidArgumentException`.
- [ ] Зависимости только примитивы, `DateTimeImmutable`, `Uuid`, `Enum` и другие VO этого слоя; нет ссылок на другие слои/Infrastructure/ORM/SDK.
- [ ] VO слоя Application не содержат бизнес-логики домена и не выражают доменные инварианты — только форматирование и преобразование данных.
- [ ] Если тип возвращается/передаётся в Domain как часть бизнес-решения и требует инвариантов, выбран VO, а не DTO.
- [ ] Методы чистые (pure); не изменяют состояние объекта и не зависят от внешнего контекста.
- [ ] При необходимости предусмотрены `equals()` / `JsonSerializable` / методы преобразования (например, `toString()`).
- [ ] Для персистентности (только Embeddable VO): используется `#[Embeddable]` в ORM‑моделях.
