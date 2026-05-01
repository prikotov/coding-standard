---
name: Listener
type: rule
description: Правила создания и использования слушателей событий
---

# Слушатель (Listener)

**Слушатель (Listener)** — элемент слоя интеграций, подписанный через конфигурацию на
[событие приложения](../application/event.md) или событие Symfony EventDispatcher
и запускающий реакцию в границах **своего** модуля. Слушатель не зависит от источника события.
Если обработка зависит от инициатора, используйте **Шину команд (Command Bus)**. Механизм регистрации
выбирается по тому, где фактически публикуется событие: в **Шине событий (Event Bus)** или в Symfony EventDispatcher.

## Общие правила

- **Назначение**: связывает событие с конкретной реакцией своего модуля.
- **Единая точка входа:** публичный `__invoke(Event $event): void`.
- **Именование:** `{Action}On{EventName}Listener`. Пример: `TrackOnReceivedListener`.
- **Границы модуля:** бизнес-реакции слушатель делегирует в
  [сценарий использования (Use Case)](../application/use-case.md) своего модуля напрямую или через компонент шины команд
  вашего приложения; технические реакции — в сервисы интеграций своего модуля.
- Событие и слушатель могут находиться в одном или разных модулях.
- Один слушатель (listener) подписывается на одно событие. Если нужно реагировать на несколько событий — создавайте
  отдельный слушатель на каждое событие.

## Зависимости

- **Разрешено:** [сценарий использования (Use Case)](../application/use-case.md)
  ([обработчик команд (CommandHandler)](../application/command-handler.md)/
  [обработчик запросов (QueryHandler)](../application/query-handler.md)) **своего** модуля, сервисы интеграций,
  мапперы/фабрики.
- **Запрещено:**
    - Прямой доступ к БД/HTTP/очередям.
    - Обращения к классам других модулей напрямую (только через сервисы интеграций).

## Расположение

- В слое [Integration](../integration.md):

```php
{ProjectName}\Common\Module\{ModuleName}\Integration\Listener\{Group?}\{Action}On{EventName}Listener
```

`{Group?}` — опциональная группа слушателей; используем только если реально нужна группировка при наличии
нескольких слушателей по смежным событиям.
`{Action}` — глагол в повелительной форме (`Track`, `Send`, `Log`, `Notify`).
`{EventName}` — имя события без суффикса `Event`.

## Как используем

1. Подписываем слушатель на событие через корректный механизм доставки:
   - `#[AsMessageHandler]` — для событий приложения/домена (application/domain events), доставляемых через Symfony
     Messenger/Event Bus;
   - `#[AsEventListener]` — для событий, публикуемых через Symfony EventDispatcher.
2. Внутри `__invoke()` — минимум кода: фильтрация, маппинг и делегация. Не размещайте бизнес-логику в слушателе.
3. Межмодульные (cross-module) потребности закрываются через сценарий использования (Use Case) и сервисный слой
   интеграций.

## Пример для Symfony Messenger/Event Bus

```php
<?php

declare(strict_types=1);

namespace ProjectName\Common\Module\Billing\Integration\Listener\Chat\ChatMessage;

use ProjectName\Common\Application\Component\CommandBus\CommandBusComponentInterface;
use ProjectName\Common\Module\Billing\Application\Enum\UsageTypeEnum;
use ProjectName\Common\Module\Billing\Application\UseCase\Command\Usage\Track\TrackCommand;
use ProjectName\Common\Module\Chat\Application\Event\ChatMessage\ReceivedEvent;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class TrackOnReceivedListener
{
    public function __construct(
        private CommandBusComponentInterface $busComponent,
    ) {
    }

    public function __invoke(ReceivedEvent $event): void
    {
        $this->busComponent->execute(new TrackCommand(
            usageType: UsageTypeEnum::chat,
            modelUri: $event->getModelUri(),
            modelUuid: null,
            userUuid: $event->getUserUuid(),
            projectUuid: $event->getProjectUuid(),
            chatUuid: $event->getChatUuid(),
            inputChatMessageUuid: $event->getInputChatMessageUuid(),
            outputChatMessageUuid: $event->getOutputChatMessageUuid(),
            tokensInputCacheHit: $event->getTokensInputCacheHit(),
            tokensInputCacheMiss: $event->getTokensInputCacheMiss(),
            tokensOutput: $event->getTokensOutput(),
            timeToFirstToken: $event->getTimeToFirstToken(),
            generationTime: $event->getGenerationTime(),
        ));
    }
}
```

## Пример для Symfony EventDispatcher

```php
<?php

declare(strict_types=1);

namespace ProjectName\Common\Module\Notification\Integration\Listener\Messenger;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

#[AsEventListener(event: WorkerMessageFailedEvent::class)]
final readonly class LogOnWorkerMessageFailedListener
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(WorkerMessageFailedEvent $event): void
    {
        $message = $event->getEnvelope()->getMessage();

        $this->logger->warning('Messenger worker message failed.', [
            'class' => $message::class,
            'transport' => $event->getReceiverName(),
            'exceptionClass' => $event->getThrowable()::class,
        ]);
    }
}
```

## Чек-лист для код ревью

- [ ] Имя по схеме `{Action}On{EventName}Listener`, namespace
  `{ProjectName}\Common\Module\{ModuleName}\Integration\Listener\{Group?}`.
- [ ] Только `__invoke()`; минимум кода, бизнес-логика не реализована внутри слушателя.
- [ ] Один слушатель подписан только на одно событие.
- [ ] Нет прямых кросс-модульных вызовов.
- [ ] Корректная регистрация: `#[AsMessageHandler]` для событий/сообщений, доставляемых через Messenger/Event Bus,
  `#[AsEventListener]` для событий Symfony EventDispatcher.
