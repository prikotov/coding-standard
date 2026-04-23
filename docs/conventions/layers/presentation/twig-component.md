---
name: Twig Component
type: rule
description: Правила создания Twig-компонентов
---

# Twig-компонент (Twig Component)

## Определение

**Twig-компонент (Twig Component)** — самостоятельный переиспользуемый UI-блок на базе [Symfony UX TwigComponent](https://symfony.com/bundles/ux-twig-component/current/index.html), который инкапсулирует presentation-состояние и шаблон блока.

Цель Twig Component:
- переиспользовать HTML-контракт и визуальное поведение UI-блоков;
- сократить дублирование верстки между страницами и модулями;
- централизовать presentation-логику конкретного визуального блока.

## Общие правила

- Класс компонента располагаем в `apps/web/src/Component/Twig/<Namespace>/` и объявляем через атрибут `#[AsTwigComponent]`.
- Классы компонентов объявляем `final`, если нет обоснованной необходимости наследования.
- Публичные свойства компонента формируют контракт входных параметров из Twig и должны быть строго типизированы.
- Логику маппинга входа в display-состояние размещаем в `mount(...)` или в вычисляемых методах компонента.
- Для общих UI-элементов используем `Phoenix` namespace; для модульных элементов — namespace модуля (`Project`, `Team`, `User` и т.д.).
- Разрешено переиспользование шаблона базового компонента (например, статусные бейджи через `components/Phoenix/Badge.html.twig`).
- В компоненте запрещена бизнес-логика и вызовы Application UseCase/Query/Command.

## Зависимости

- Разрешено: route-сервисы Presentation, enum/DTO для отображения, простые helper-сервисы уровня UI.
- Запрещено: типы и сервисы из `Domain/*`, репозитории, ORM, сервисы/типы из `Infrastructure/*`, прямые вызовы `Application/*` use case и внешних API.

## Расположение

```
apps/web/src/Component/Twig/<Namespace>/<Component>.php
apps/web/templates/components/<Namespace>/<Component>.html.twig
apps/web/tests/Unit/Component/Twig/<Namespace>/<Component>Test.php
```

## Как используем

1. Создаём класс компонента с `#[AsTwigComponent]` и описываем публичные входные свойства.
2. Для нестандартного имени/пути задаём `name` и `template` в атрибуте.
3. Переносим presentation-преобразования в `mount(...)` и методы компонента, а не в Twig-шаблон страницы.
4. Используем компонент в шаблоне через `<twig:Namespace:Component .../>` или `{{ component('Namespace:Component', {...}) }}`.
5. Проверяем компонент unit-тестом (маппинг, вычисления, edge cases), а итоговый HTML-контракт — integration/e2e тестом по сценарию страницы.

## Когда использовать Twig Component

- Когда нужен самостоятельный переиспользуемый UI-блок с собственным HTML-контрактом.
- Когда визуальный блок имеет собственное presentation-состояние (props, `mount(...)`, вычисляемые методы).
- Когда нужно централизовать повторяющуюся верстку и поведение блока в `templates/components/...`.
- Когда блок должен вызываться как полноценный компонент шаблона, а не как helper-вычисление.
- Для правил по Twig Extension используем документ [Twig Extension](twig-extension.md).

### Быстрый decision checklist

- [ ] Нужно переиспользовать HTML-блок с параметрами отображения -> `Twig Component`.
- [ ] Компоненту нужна собственная template-структура в `templates/components/...` -> `Twig Component`.
- [ ] Нужна инкапсуляция presentation-состояния конкретного визуального блока -> `Twig Component`.

## Пример

```php
<?php

declare(strict_types=1);

namespace {AppName}\Web\Component\Twig\Project;

use Common\Module\Project\Application\Enum\ProjectStatusEnum;
use InvalidArgumentException;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use {AppName}\Web\Component\Twig\Phoenix\Badge;

#[AsTwigComponent(template: 'components/Phoenix/Badge.html.twig')]
final class StatusBadge extends Badge
{
    public function mount(ProjectStatusEnum $status): void
    {
        $this->type = match ($status) {
            ProjectStatusEnum::new => Badge::WARNING,
            ProjectStatusEnum::closed => Badge::SECONDARY,
            ProjectStatusEnum::active => Badge::SUCCESS,
            ProjectStatusEnum::deleted => Badge::DANGER,
            default => throw new InvalidArgumentException(sprintf('Unknown project status: "%s".', $status->value)),
        };

        $this->text = $status->value;
    }
}
```

```twig
<twig:Project:StatusBadge status="{{ project.status }}" />
<twig:Project:MembersAvatarGroup :users="assignees.items" size="xl" />
```

## Чек-лист для проведения ревью кода

- [ ] Компонент расположен в `apps/web/src/Component/Twig/<Namespace>/` и имеет соответствующий шаблон в `templates/components/`.
- [ ] Контракт входа выражен через типизированные публичные свойства/`mount(...)`.
- [ ] В компоненте нет бизнес-логики, UseCase-вызовов и зависимостей из Infrastructure/Integration.
- [ ] Шаблон компонента не дублирует вычисления, которые уже есть в классе компонента.
- [ ] Для новой логики компонента добавлены unit/integration проверки соответствующего уровня.
