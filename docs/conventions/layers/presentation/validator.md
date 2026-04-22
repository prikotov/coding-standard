---
name: Validator
type: rule
description: Правила создания валидаторов презентационного слоя
---

# Validator презентационного слоя (Presentation Validator)

## Определение

**Presentation Validator** — custom pair из metadata-класса `*Constraint` и исполняющего класса
`*ConstraintValidator`, который инкапсулирует reusable или cross-field validation для `RequestDto`, `QueryDto`,
`FormModel` и других transport model слоя Presentation.

## Общие правила

- Naming pattern обязателен: один semantic stem и суффиксы `Constraint` / `ConstraintValidator`.
- `Constraint` хранит только message, options, target (`PROPERTY_CONSTRAINT` / `CLASS_CONSTRAINT`) и другую metadata.
- `ConstraintValidator` содержит только validation logic и работу с `ExecutionContext`.
- Property-level правила используем для одного поля; class-level — для cross-field validation.
- Если правило живёт более чем в одном DTO/FormModel, требует отдельного имени, class-level contract или читаемого reuse,
  оно должно жить во внешнем validator pair, а не в `Callback`/`validate*()`.
- Validation layer не должен выполнять I/O и не должен реализовывать business rules.

## Зависимости

### Разрешено

- `Symfony\Component\Validator\Constraint`, `ConstraintValidator`, `ExecutionContextInterface`.
- Валидируемые presentation DTO/FormModel из того же app/module.
- Чистые PHP helpers и deterministic parsing, не выходящие во внешнюю среду.

### Запрещено

- QueryBus, CommandBus, handlers, repositories, ORM, HTTP clients, filesystem, queues.
- Business-решения и доменные инварианты, которые должны жить в Application/Domain.
- Зависимости на Infrastructure/Integration implementations.

## Расположение

- Module-local validator:

```
apps/<app>/src/Module/<ModuleName>/Validation/Constraint/<Name>Constraint.php
apps/<app>/src/Module/<ModuleName>/Validation/Constraint/<Name>ConstraintValidator.php
```

- Cross-cutting validator:

```
apps/<app>/src/Component/Validation/Constraint/<Name>Constraint.php
apps/<app>/src/Component/Validation/Constraint/<Name>ConstraintValidator.php
```

## Service Wiring

- Если каталог validator classes уже покрыт module/app `services.yaml` с `autowire: true` и `autoconfigure: true`,
  отдельный тег не нужен.
- Если path исключён из service discovery, validator нужно зарегистрировать явно и добавить тег
  `validator.constraint_validator`.
- При переносе validation logic сначала проверяем фактический DI boundary приложения, а не предполагаем автоподхват.

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

Такой pair выносит reusable cross-field validation из DTO/FormModel во внешний presentation validator.

## Чек-лист код-ревью

- [ ] Naming следует паттерну `*Constraint` / `*ConstraintValidator`.
- [ ] `Constraint` хранит только metadata/options, без runtime logic.
- [ ] `ConstraintValidator` не делает I/O и не тянет business dependencies.
- [ ] Class-level validation вынесена из DTO/FormModel во внешний validator pair.
- [ ] Service wiring подтверждён для конкретного app/module.
