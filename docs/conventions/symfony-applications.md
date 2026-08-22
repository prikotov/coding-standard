---
package: prikotov/coding-standard
name: Symfony Applications
type: rule
description: Правила организации изолированных Symfony-приложений
---

# Приложения на фреймворке Symfony (Symfony Applications)

**Приложение Symfony (Symfony Application)** — изолированный экземпляр ядра Symfony (Kernel) с собственным набором модулей, конфигурацией и назначением. Подробности: [Приложения Symfony](https://symfony.com/doc/current/configuration/multiple_applications.html).

## Общие правила

- Каждое приложение находится в директории `apps/<app_name>/`.
- Все приложения наследуются от общего [`ProjectName\Common\Kernel`](examples/Kernel.php).
- Каждое приложение имеет собственный идентификатор (`id`), который используется для разделения кэша и логов.
- Конфигурация приложения находится в `apps/<app_name>/config/`.
- Модули приложения регистрируются в `apps/<app_name>/config/modules.php`.
- Приложения могут переопределять или дополнять общую конфигурацию из `config/`.
- Каждое приложение имеет собственные тесты в `apps/<app_name>/tests/`.

## Структура приложения

```
apps/<app_name>/
├── config/
│   ├── bundles.php          # Регистрация bundles приложения
│   ├── modules.php          # Регистрация модулей приложения
│   ├── packages/            # Конфигурация пакетов
│   ├── routes/              # Маршруты приложения
│   └── services.yaml        # Конфигурация сервисов
├── src/                     # Исходный код приложения
│   ├── Component/           # Компоненты приложения
│   ├── Controller/          # Контроллеры
│   ├── EventSubscriber/     # Подписчики событий
│   ├── Module/              # Модули приложения
│   └── Security/            # Безопасность
├── templates/               # Шаблоны (при необходимости)
├── tests/                   # Тесты приложения
└── translations/            # Переводы приложения
```

## Расположение

```
apps/<app_name>/
```

## Назначение приложений

Приложения разделяются по назначению — каждое со своим набором модулей, конфигурацией и тестами. Состав модулей зависит от назначения и фиксируется в `apps/<app_name>/config/modules.php`.

Примеры типов приложений:

- **Web** (`apps/web`) — пользовательский интерфейс: Twig, формы, аутентификация, UI-компоненты.
- **API** (`apps/api`) — REST/JSON API для внешних клиентов.
- **Консоль** (`apps/console`) — CLI-команды, фоновые задачи, обработка очередей.

Конкретный набор приложений и модулей определяется проектом.

## Общее ядро (Kernel)

Все приложения наследуются от [`ProjectName\Common\Kernel`](examples/Kernel.php), который реализует:

- **`ModuleKernelTrait`** — поддержка модульной системы.
- **`MicroKernelTrait`** — гибкая конфигурация через PHP.
- **Разделение конфигурации** — общая (`config/`) и приложения (`apps/<app_name>/config/`).
- **Разделение модулей** — общие (`config/modules.php`) и приложения (`apps/<app_name>/config/modules.php`).
- **Разделение кэша и логов** — по идентификатору приложения.

### Пример ядра (Kernel) приложения

```php
<?php

declare(strict_types=1);

namespace ProjectName\<AppName>;

use ProjectName\Common\Kernel as CommonKernel;

final class Kernel extends CommonKernel
{
}
```

## Конфигурация приложений

### Регистрация модулей

Модули приложения регистрируются в `apps/<app_name>/config/modules.php`:

```php
<?php

declare(strict_types=1);

return [
    ProjectName\Web\Module\Chat\ChatModule::class => ['all' => true],
    ProjectName\Web\Module\Project\ProjectModule::class => ['all' => true],
];
```

### Регистрация пакетов (bundles)

Пакеты (bundles) приложения регистрируются в `apps/<app_name>/config/bundles.php`:

```php
<?php

declare(strict_types=1);

return [
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
    Symfony\Bundle\TwigBundle\TwigBundle::class => ['all' => true],
];
```

### Конфигурация сервисов

Конфигурация сервисов приложения находится в `apps/<app_name>/config/services.yaml`:

```yaml
services:
    _defaults:
        autowire: true
        autoconfigure: true

    App\:
        resource: '../src/'
        exclude:
            - '../src/DependencyInjection/'
```

### Маршруты приложения

Маршруты приложения находятся в `apps/<app_name>/config/routes/`:

```yaml
# apps/web/config/routes/dashboard.yaml
dashboard:
    path: /dashboard
    controller: ProjectName\Web\Module\Dashboard\Controller\DashboardController::index
```

## Как используем

- **Создание нового приложения**: создайте директорию `apps/<app_name>/` с необходимой структурой и ядром (Kernel), наследуемым от `ProjectName\Common\Kernel`.
- **Добавление модуля в приложение**: зарегистрируйте модуль в `apps/<app_name>/config/modules.php`.
- **Переопределение конфигурации**: создайте файл конфигурации в `apps/<app_name>/config/` для переопределения общих настроек.
- **Разделение тестов**: размещайте тесты в `apps/<app_name>/tests/` для изоляции тестов разных приложений.
- **Разделение кэша и логов**: используйте идентификатор приложения для автоматического разделения директорий.

## Чек-лист для проведения ревью кода

- [ ] Приложение имеет правильную структуру директорий.
- [ ] Ядро приложения наследуется от `ProjectName\Common\Kernel`.
- [ ] Модули зарегистрированы в `apps/<app_name>/config/modules.php`.
- [ ] Пакеты (bundles) зарегистрированы в `apps/<app_name>/config/bundles.php`.
- [ ] Конфигурация сервисов находится в `apps/<app_name>/config/services.yaml`.
- [ ] Маршруты приложения находятся в `apps/<app_name>/config/routes/`.
- [ ] Тесты приложения находятся в `apps/<app_name>/tests/`.
- [ ] Нет дублирования модулей между `config/modules.php` и `apps/<app_name>/config/modules.php`.
- [ ] Кэш и логи разделены по идентификатору приложения.
- [ ] Документация соответствует правилам из `docs/conventions/doc-writing-rules.md`.
