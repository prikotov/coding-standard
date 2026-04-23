---
name: Middleware
type: rule
description: Правила создания и использования middleware в pipeline
---

# Middleware

**Middleware** — элемент слоя интеграций, встраиваемый в pipeline внешнего фреймворка или транспорта
(`Symfony Messenger`, HTTP pipeline, queue consumer lifecycle) для технической адаптации данных и контекста перед
передачей управления дальше.

Middleware нужен, когда проекту требуется перехватить framework-specific контекст и преобразовать его в удобную для
следующего слоя форму, не протаскивая детали внешнего механизма в `Application` или `Domain`.

## Общие правила

- **Назначение:** техническая адаптация framework/transport context, а не реализация бизнес-логики.
- **Именование:** `{Subject}{Purpose}Middleware`. Пример: `WebhookDeliveryAttemptMiddleware`.
- **Размещение:** сначала тип артефакта, потом технологический контекст:
  `Integration\Middleware\{Technology}\{Name}Middleware`.
- **Область ответственности:** читает/добавляет framework metadata (`Stamp`, headers, context attributes), выполняет
  guard-проверки, делегирует выполнение дальше по pipeline.
- **Границы:** не заменяет `Use Case`, `Service` или `Component`.

## Зависимости

- **Разрешено:**
  - framework contracts и transport-specific типы (`Envelope`, `Stamp`, middleware interfaces);
  - DTO/Command/Event своего модуля для точечного ветвления;
  - общие компоненты приложения, если middleware действительно кросс-секционный.
- **Запрещено:**
  - бизнес-логика;
  - прямой доступ к БД, HTTP-клиентам, очередям, файловой системе;
  - orchestration use case-сценария;
  - маскировка middleware под `Component`.

## Когда это `Middleware`, а не `Component`

- Используйте **Middleware**, если класс живёт внутри lifecycle внешнего фреймворка и работает через его контракт
  (`MiddlewareInterface`, pipeline API, consumer hooks).
- Используйте **Component**, если нужен переносимый адаптер к внешнему API/SDK/ресурсу с собственным контрактом
  `*ComponentInterface`.

`Symfony Messenger` как технология является внешним техническим миром, но не каждый адаптер вокруг него становится
`Component`. Если класс завязан на конкретный message pipeline и знает о внутренних message/command типах модуля, это
`Middleware`, а не `Component`.

## Расположение

- **Module Integration**

```php
{ProjectName}\Common\Module\{ModuleName}\Integration\Middleware\{Technology}\{Name}Middleware
```

- **Shared Infrastructure**

Если middleware переиспользуется на уровне всей платформы, а не одного модуля, размещайте его в:

```php
Common\Infrastructure\Component\{Technology}\Middleware\{Name}Middleware
```

## Как используем

1. Размещаем module-specific middleware в `Integration\Middleware\{Technology}`.
2. Внутри middleware оставляем только техническую адаптацию и передачу управления дальше.
3. Если middleware становится кросс-модульным и не зависит от application-классов конкретного модуля, переносим его в
   shared `Infrastructure\Component`.

## Пример

```php
<?php

declare(strict_types=1);

namespace ProjectName\Common\Module\Webhook\Integration\Middleware\Messenger;

use ProjectName\Common\Module\Webhook\Application\UseCase\Command\WebhookDelivery\Deliver\DeliverCommand;
use Override;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\HandlerArgumentsStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;

final readonly class WebhookDeliveryAttemptMiddleware implements MiddlewareInterface
{
    #[Override]
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        if (!$envelope->getMessage() instanceof DeliverCommand) {
            return $stack->next()->handle($envelope, $stack);
        }

        /** @var RedeliveryStamp|null $redeliveryStamp */
        $redeliveryStamp = $envelope->last(RedeliveryStamp::class);
        $attempt = ($redeliveryStamp?->getRetryCount() ?? 0) + 1;

        return $stack->next()->handle(
            $envelope->with(new HandlerArgumentsStamp([$attempt])),
            $stack,
        );
    }
}
```

## Чек-лист для код ревью

- [ ] Класс действительно встроен в lifecycle внешнего framework/transport pipeline.
- [ ] В классе нет бизнес-логики и orchestration.
- [ ] Имя оканчивается на `Middleware`.
- [ ] Namespace следует схеме `Integration\Middleware\{Technology}`.
- [ ] Если реализация кросс-модульная, рассмотрен перенос в `Common\Infrastructure\Component\{Technology}\Middleware`.
