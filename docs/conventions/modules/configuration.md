---
package: prikotov/coding-standard
name: Module Configuration
type: rule
description: Шаги по подключению модуля к общему ядру и конфигурации
---

# Конфигурирование модулей

Этот документ описывает базовые шаги по подключению модуля к общему ядру проекта и приведению его конфигурации к единым правилам. Прежде чем создавать новый модуль проверьте, что он соответствует DDD-структуре Domain, Application, Infrastructure, Integration и что конфигурация лежит в `Resource/config`.

## Общие правила

- Каждый модуль реализует `ModuleInterface` и регистрируется в `config/modules.php`.
- Параметры модуля именуются `module.<module_name>.<context>`.
- Несервисные типы (Entity, Enum, VO, DTO, Event) исключаются из автоконфигурации.
- Репозитории внедряются через интерфейсы из Domain.
- Секреты — через `%env()%`, не хардкод.

## Расположение

```
src/Module/{ModuleName}/
├── {ModuleName}Module.php
├── Resource/
│   └── config/
│       └── services.yaml
```

## Базовая структура модуля

1. Создайте класс `<ModuleName>Module` в корне модуля (`src/Module/<ModuleName>/<ModuleName>Module.php`).
2. Реализуйте как минимум `ModuleInterface` и верните пути к каталогу модуля:

```php
<?php

declare(strict_types=1);

namespace ProjectName\Common\Module\Billing;

use ProjectName\Common\Component\ModuleSystem\Extension\DoctrineInterface;
use ProjectName\Common\Component\ModuleSystem\ModuleInterface;
use Override;

final class BillingModule implements ModuleInterface, DoctrineInterface
{
    #[Override]
    public function getModuleDir(): string
    {
        return __DIR__;
    }

    #[Override]
    public function getModuleConfigPath(): string
    {
        return $this->getModuleDir() . '/Resource/config';
    }
}
```

3. Если модуль предоставляет Twig-шаблоны, переводы или дополнительные расширения, реализуйте соответствующие интерфейсы (`TwigInterface`, `TranslationInterface` и т.п.). Пример полного списка интерфейсов есть в `ProjectName\Common\Module\Billing\BillingModule`.
4. Добавьте модуль в `config/modules.php`. Для приложений из каталога `apps/*` используйте их собственные `config/modules.php`.

## Конфигурация сервисов

Каждый модуль должен иметь `Resource/config/services.yaml`. В нём объявляются параметры и сервисы модуля. Подробности: [Контейнер сервисов Symfony](https://symfony.com/doc/current/service_container.html).

- Используйте параметры вида `module.<module_name>.<context>`, чтобы избежать конфликтов имён. Подробности: [Параметры сервисов](https://symfony.com/doc/current/service_container.html#service-parameters).
- Для импорта каталога с сервисами применяйте `resource: '%module.<module_name>.module_dir%/'` с исключениями (`exclude`) для всех несервисных структурных типов. Обязательный минимум: `Resource`, `Domain/Entity`, файлы `*Dto.php`, `*Event.php`, `*Exception.php`, `*Enum.php`, `*Vo.php`, `*Command.php`, `*Query.php`, `<ModuleName>Module.php`. Используйте исключения по суффиксу, чтобы покрыть не только общие каталоги (`Application/Dto`, `Domain/ValueObject`), но и контекстные расположения рядом с use case, domain service, component или адаптером. Если в модуле есть отдельные каталоги с другими классами данных или value object, исключайте и их тоже, чтобы контейнер не регистрировал их как сервисы. Подробности: [Импорт файлов конфигурации](https://symfony.com/doc/current/service_container/imports.html).
- Значения из переменных окружения подключайте через `%env()%`. Старайтесь документировать обязательные переменные в `.env.dist` или `AGENTS.md` соответствующего модуля. Подробности: [Переменные окружения](https://symfony.com/doc/current/configuration.html#environment-variables).

Пример конфигурации сервисов модуля `Common` (`src/Module/Billing/Resource/config/services.yaml`):

```yaml
parameters:
  module.billing.module_dir: '%kernel.project_dir%/src/Module/Billing'

services:
  _defaults:
    autowire: true
    autoconfigure: true

  ProjectName\Common\Module\Billing\:
    resource: '%module.billing.module_dir%/'
    exclude:
      - '%module.billing.module_dir%/Resource/'
      - '%module.billing.module_dir%/Domain/Entity/'
      - '%module.billing.module_dir%/**/*Dto.php'
      - '%module.billing.module_dir%/**/*Event.php'
      - '%module.billing.module_dir%/**/*Exception.php'
      - '%module.billing.module_dir%/**/*Enum.php'
      - '%module.billing.module_dir%/**/*Vo.php'
      - '%module.billing.module_dir%/Application/UseCase/Command/**/*Command.php'
      - '%module.billing.module_dir%/Application/UseCase/Query/**/*Query.php'
      - '%module.billing.module_dir%/BillingModule.php'
```

Такой конфиг подключает все классы модуля и одновременно исключает из автоконфигурации несервисные типы: сущности, enum, value object, DTO, события, исключения, команды/запросы и служебные файлы модуля. Благодаря этому контейнер содержит только реальные сервисы, а Doctrine продолжает сама управлять жизненным циклом сущностей. Если проект использует устаревший каталог `Resources`, добавьте его в `exclude` рядом с `Resource`. Классы, которые настраиваются вручную как декораторы, `factory-service` или псевдонимы, исключайте точечно. Подробности: [Автовнедрение зависимостей](https://symfony.com/doc/current/service_container/autowiring.html).

### Автоматическая проверка исключений

Проверяйте конфигурацию модулей из корня проекта:

```bash
vendor/bin/coding-standard-di-check
```

Команда проверяет:

- каждый авто-импорт `resource` в модульном `services.yaml` исключает несервисные типы общими суффиксными масками (`**/*Dto.php` и остальной обязательный минимум), а не перечислением отдельных файлов или каталогов — покрытие должно работать для любого вложенного расположения класса. Common-модули несут обязательный минимум независимо от содержимого; модули приложений и корневые импорты `apps/*` — маски для типов, фактически присутствующих в дереве: требование включается вместе с первым классом типа. Импорты из `vendor/` не проверяются — сторонние пакеты следуют своим конвенциям;
- ни один сервис не внедряет `Command`, `Query`, DTO и другие несервисные классы через конструктор — сообщения и объекты данных передаются аргументами методов.

Symfony console `Command` из Presentation-слоя не совпадают с Application-командами (`Application\UseCase\Command\...`), поэтому проверку не нарушают и из контейнера не исключаются. Команда `coding-standard-init` подключает проверку в цель `check` Makefile.

## Конфигурация работы с Doctrine-сущностями

> Глава основана на `ProjectName\Common\Module\Billing\BillingModule` и `src/Module/Billing/Resource/config/services.yaml`.

Поддержка Doctrine в модуле включает три шага:

1. **Реализуйте `DoctrineInterface`.** В `<ModuleName>Module` опишите методы:
    - `getEntityNamespace()` — корневое пространство имён сущностей (обычно `__NAMESPACE__ . '\Domain\Entity'`).
    - `getMappingPath()` — путь к каталогу, где лежат классы-сущности. Трейт `ModuleKernelTrait` автоматически зарегистрирует этот каталог в `DoctrineOrmMappingsPass`, если директория существует.

    ```php
    #[Override]
    public function getEntityNamespace(): string
    {
        return __NAMESPACE__ . '\Domain\Entity';
    }

    #[Override]
    public function getMappingPath(): string
    {
        return $this->getModuleDir() . '/Domain/Entity';
    }
    ```

2. **Разместите сущности в `Domain/Entity`.** Сущности оформляются через атрибуты Doctrine, используют постфикс `Model` и технические трейты (`IdTrait`, `UuidTrait`, `InsTsTrait`). Пример можно найти в `ProjectName\Common\Module\Billing\Domain\Entity\PaymentModel`.

3. **Настройте сервисы Doctrine.**
    - Не регистрируйте сущности как сервисы в `services.yaml`. Исключения в разделе `exclude` защищают от автоконфигурации, чтобы Doctrine создавала сущности сама.
    - Не регистрируйте как сервисы и другие несервисные структурные типы модуля: Enum, VO, DTO, Event, Exception, Command, Query и аналогичные классы данных или value object. Если такие каталоги выделены отдельно, добавляйте их в `exclude`.
    - Репозитории внедряйте через интерфейсы (`Domain\Repository`) и реализации в `Infrastructure\Repository`. Сами реализации автоматически загружаются благодаря `resource` в `services.yaml`.
    - Если модулю нужен отдельный `EntityManager`, добавьте конфигурацию в `Resource/config/doctrine.yaml` (по умолчанию проект использует общий `manager`, поэтому файл необязателен).

4. **Проверьте миграции.** Создание таблиц для новых сущностей выполняйте через `bin/console make:migration`. Скрипты миграций лежат в корне проекта внутри `migrations/`.

После этих шагов сущности модуля доступны в общем `EntityManager`. Для тестов создавайте фикстуры или фабрики внутри модуля, а интеграционные тесты наследуйте от `ProjectName\Common\Component\Test\KernelTestCase`, чтобы контейнер модуля полностью инициализировался.

### Чек-лист

- [ ] Модуль реализует `DoctrineInterface` и возвращает корректные пути к сущностям.
- [ ] В `services.yaml` из автоконфигурации исключены сущности и остальные несервисные структурные типы модуля.
- [ ] Репозитории регистрируются через интерфейсы и лежат в `Infrastructure\Repository`.
- [ ] Все обязательные параметры описаны и привязаны к `%env()%`.
- [ ] Добавлены миграции для новых таблиц.

## Особенности модулей web-приложения

Web-клиент (`apps/web`) использует тот же модульный подход, но с дополнительными правилами конфигурации:

1. **Отдельный `modules.php`.** Каждый модуль, который должен работать только в web-приложении, регистрируйте в `apps/web/config/modules.php`. Это особенно важно для UI-компонентов, контроллеров `Stimulus` и Twig-компонентов, которые не нужны в других приложениях.
2. **Twig и компоненты.** Если модуль предоставляет шаблоны, реализуйте `TwigInterface` в `<ModuleName>Module` и держите шаблоны в `apps/web/src/Module/<ModuleName>/Resource/templates`. Имена, которые будут подключаться как `@web.<module>/...`, формируются автоматически из пространств имён.
3. **Переводы.** Строки, относящиеся к конкретному модулю, размещайте только в `apps/web/src/Module/<ModuleName>/Resource/translations/messages.<locale>.yaml` и подключайте через `TranslationInterface`. Файлы `apps/web/translations/*` предназначены для действительно глобальных сообщений (навигация, layout и т.п.). Это правило предотвращает пересечения ключей между модулями.
4. **`AssetMapper` / `Stimulus`.** Статические ресурсы и контроллеры должны лежать внутри директории модуля (`Resource/assets`, `Resource/stimulus`). Подключать их нужно через соответствующие конфиги в `Resource/config/assets.yaml` и `Resource/config/stimulus.yaml`, которые импортируются из `apps/web/config/modules.php`.
5. **Тесты UI.** Интеграционные тесты web-модулей размещайте в `apps/web/tests/Integration/Module/<ModuleName>`, чтобы они могли инициализировать нужное приложение и его маршруты.

Следуя этим правилам, переводы и компоненты остаются изолированными внутри модуля, а общие каталоги приложения не зарастают модульным кодом.

### Пример конфигурации Presentation-модуля

Для модулей слоя Presentation используйте префикс конкретного приложения параметров: `<app_name>.module.<module_name>.<context>`.

Пример `apps/web/src/Module/Source/Resource/config/services.yaml`:

```yaml
parameters:
  web.module.source.module_dir: '%kernel.project_dir%/apps/web/src/Module/Source'

services:
  _defaults:
    autowire: true
    autoconfigure: true

  ProjectName\Web\Module\Source\:
    resource: '%web.module.source.module_dir%/'
    exclude:
      - '%web.module.source.module_dir%/Resource/'
      - '%web.module.source.module_dir%/**/*Dto.php'
      - '%web.module.source.module_dir%/**/*Enum.php'
      - '%web.module.source.module_dir%/**/*FormModel.php'
      - '%web.module.source.module_dir%/**/*Vo.php'
      - '%web.module.source.module_dir%/**/*Constraint.php'
      - '%web.module.source.module_dir%/SourceModule.php'
```

Если модулю нужны псевдонимы, именованные ограничители частоты или подмены только для тестов, добавляйте их отдельными сервисными объявлениями после общего импорта.

## Чек-лист для проведения ревью кода

- [ ] Модуль реализует `ModuleInterface` (и `DoctrineInterface`, если есть сущности).
- [ ] Модуль зарегистрирован в `config/modules.php`.
- [ ] Параметры именуются `module.<module_name>.<context>` для модулей `Common` или `<app_name>.module.<module_name>.<context>` для модулей приложения.
- [ ] Entity, Enum, VO, DTO, Event, Exception и Command/Query исключены из автоконфигурации.
- [ ] Репозитории внедряются через доменные интерфейсы.
- [ ] Обязательные переменные окружения документированы в `.env.dist`.
