---
name: Domain слой
type: rule
description: Доменный слой: бизнес-логика, сущности и контракты
---

# Доменный слой (Domain Layer)

**Доменный слой (Domain Layer)** — ядро бизнес-логики приложения, содержащее сущности, объекты-значения, контракты репозиториев и доменные сервисы. Не зависит от других слоёв.

## Общие правила

- Domain не зависит от Application, Infrastructure, Integration, Presentation.
- Бизнес-логика реализуется в Domain: инварианты, вычисления, спецификации.
- Репозитории определяются как интерфейсы (контракты) в Domain.
- Сущности и VO — `final` классы с `declare(strict_types=1)`.

## Расположение

```
src/Module/{ModuleName}/Domain/
├── Entity/
├── Enum/
├── ValueObject/
├── Repository/
│   └── {EntityName}/
│       ├── {EntityName}RepositoryInterface.php
│       └── Criteria/
├── Service/
│   └── Integration/
│       └── {ServiceName}Interface.php
├── Specification/
│   └── {SpecificationName}.php
└── Calculator/
    └── {CalculatorName}.php
```

## Чек-лист для проведения ревью кода

- [ ] Domain не зависит от других слоёв.
- [ ] Бизнес-логика реализована в Domain, а не в Application/Infrastructure.
- [ ] Репозитории — интерфейсы, а не реализации.
- [ ] Сущности проверяют инварианты.
- [ ] VO неизменяемы и инкапсулируют валидацию.

