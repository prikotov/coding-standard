---
name: Validator
type: rule
description: Правила создания валидаторов презентационного слоя
---

# Validator презентационного слоя (Presentation Validator)

## Определение

**Presentation Validator** — пользовательская пара (pair) из класса метаданных `*Constraint` и исполняющего класса `*ConstraintValidator`, который инкапсулирует переиспользуемую или межполевую валидацию (cross-field validation) для Request DTO, Query DTO, `FormModel` и других транспортных моделей слоя Presentation.

## Общие правила

- Шаблон именования (naming pattern) обязателен: один смысловой корень (semantic stem) и суффиксы `Constraint` / `ConstraintValidator`.
- `Constraint` хранит только сообщение (message), параметры (options), область применения (target: `PROPERTY_CONSTRAINT` / `CLASS_CONSTRAINT`) и другие метаданные.
- `ConstraintValidator` содержит только логику валидации и работу с `ExecutionContext`.
- Правила уровня свойства (property-level) используем для одного поля; уровня класса (class-level) — для межполевой валидации.
- Если правило живёт более чем в одном DTO/`FormModel`, требует отдельного имени, контракта уровня класса или читаемого переиспользования, оно должно жить во внешней паре валидаторов (validator pair), а не в `Callback`/`validate*()`.
- Слой валидации не должен выполнять I/O и не должен реализовывать бизнес-правила.

## Зависимости

### Разрешено

- `Symfony\Component\Validator\Constraint`, `ConstraintValidator`, `ExecutionContextInterface`.
- Валидируемые Presentation DTO/`FormModel` из того же приложения/модуля.
- Чистые PHP-Helper и детерминированный разбор, не выходящие во внешнюю среду.

### Запрещено

- `QueryBus`, `CommandBus`, обработчики, репозитории, ORM, HTTP-клиенты, файловая система, очереди.
- Бизнес-решения и доменные инварианты, которые должны жить в Application/Domain.
- Зависимости от реализаций Infrastructure/Integration.

## Расположение

- Локальный валидатор модуля (module-local):

```
apps/<app>/src/Module/<ModuleName>/Validation/Constraint/<Name>Constraint.php
apps/<app>/src/Module/<ModuleName>/Validation/Constraint/<Name>ConstraintValidator.php
```

- Сквозной валидатор (cross-cutting):

```
apps/<app>/src/Component/Validation/Constraint/<Name>Constraint.php
apps/<app>/src/Component/Validation/Constraint/<Name>ConstraintValidator.php
```

## Подключение сервисов (Service Wiring)

- Если каталог классов-валидаторов уже покрыт `services.yaml` приложения/модуля с `autowire: true` и `autoconfigure: true`, отдельный тег не нужен.
- Если путь исключён из обнаружения сервисов (service discovery), валидатор нужно зарегистрировать явно и добавить тег `validator.constraint_validator`.
- При переносе логики валидации сначала проверяем фактическую границу DI приложения, а не предполагаем автоподхват.

## Пример

```php
use Attribute;
use Symfony\Component\Validator\Constraint;

#[Attribute(Attribute::TARGET_CLASS)]
final class PasswordsMatchConstraint extends Constraint
{
    public string $message = 'Passwords must match.';
}
```

```php
interface PasswordAwareInput
{
    public function password(): string;

    public function confirmPassword(): string;
}

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

final class PasswordsMatchConstraintValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$value instanceof PasswordAwareInput) {
            return;
        }

        if ($value->password() === $value->confirmPassword()) {
            return;
        }

        $this->context
            ->buildViolation($constraint->message)
            ->atPath('confirmPassword')
            ->addViolation();
    }
}
```

Такая пара выносит переиспользуемую межполевую валидацию из DTO/`FormModel` во внешний Presentation Validator.

## Чек-лист код-ревью

- [ ] Именование следует паттерну `*Constraint` / `*ConstraintValidator`.
- [ ] `Constraint` хранит только метаданные/параметры, без логики времени выполнения.
- [ ] `ConstraintValidator` не делает I/O и не тянет бизнес-зависимости.
- [ ] Валидация уровня класса вынесена из DTO/`FormModel` во внешнюю пару валидаторов.
- [ ] Подключение сервисов подтверждено для конкретного приложения/модуля.
