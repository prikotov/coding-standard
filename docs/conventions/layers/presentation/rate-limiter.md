---
name: Rate Limiter
type: rule
description: Правила ограничения частоты запросов
---

# Ограничение частоты запросов (Rate Limiter)

**Ограничение частоты запросов (Rate Limiter)** — presentation-механизм защиты публичных и чувствительных endpoint'ов от abuse, enumeration и burst-нагрузки на базе Symfony RateLimiter.

## Общие правила

- Лимитер конфигурируем в `apps/<app>/config/packages/rate_limiter.yaml`.
- В `apps/<app>/src/Module/<Module>/Resource/config/services.yaml` задаём alias для `RateLimiterFactoryInterface` по имени аргумента.
- В `apps/web` лимит применяем явно в controller или другом presentation-классе, который формирует HTTP-ответ.
- Не прячем rate limiting за кастомными attributes, listeners или другой неявной магией без отдельной архитектурной задачи.
- Ключ limiter'а строим из внешнего идентификатора запроса. По умолчанию используем IP: `$request->getClientIp() ?? ''`.
- После `consume(1)` сразу завершаем request при отказе, без вызова Application-слоя.
- Для HTML/form flow выбрасываем `TooManyRequestsHttpException`.
- Для JSON endpoint'ов возвращаем `429 Too Many Requests` и заголовок `Retry-After`.
- Если endpoint имеет свой JSON-контракт ошибки, ответ `429` должен соответствовать этому контракту.

## Зависимости

- Разрешено: `RateLimiterFactoryInterface`, `Request`, `Response`/`JsonResponse`, presentation response factory.
- Запрещено: внедрять limiter в `Domain`/`Application`, вызывать limiter после UseCase, использовать инфраструктурные реализации напрямую.

## Расположение

```php
apps/<app>/config/packages/rate_limiter.yaml
apps/<app>/src/Module/<Module>/Resource/config/services.yaml
apps/<app>/src/Module/<Module>/Controller/<Context>/<Action>Controller.php
```

## Как используем

1. Описываем limiter в `rate_limiter.yaml`.
2. Пробрасываем нужный factory через alias в модульный `services.yaml`.
3. В начале controller создаём limiter по внешнему ключу и вызываем `consume(1)`.
4. При отказе сразу возвращаем `429` или бросаем `TooManyRequestsHttpException`.
5. Только после успешного `consume()` вызываем Application-слой.

## Пример

```php
<?php

declare(strict_types=1);

namespace AppName\Web\Module\Chat\Controller\PublicChat;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

final readonly class SendMessageController
{
    public function __construct(
        private RateLimiterFactoryInterface $publicChatLimiter,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $limiter = $this->publicChatLimiter->create($request->getClientIp() ?? '');
        $limit = $limiter->consume(1);
        if ($limit->isAccepted()) {
            return new JsonResponse(['status' => 'ok']);
        }

        $retryAfterSeconds = max(1, $limit->getRetryAfter()->getTimestamp() - time());

        return new JsonResponse([
            'status' => 'error',
            'error' => [
                'code' => 'rate_limited',
                'retryAfterSeconds' => $retryAfterSeconds,
            ],
        ], Response::HTTP_TOO_MANY_REQUESTS, [
            'Retry-After' => (string) $retryAfterSeconds,
        ]);
    }
}
```

## Чек-лист для ревью

- [ ] Лимитер объявлен в `rate_limiter.yaml`.
- [ ] `RateLimiterFactoryInterface` подключён через alias в модульном `services.yaml`.
- [ ] Проверка выполняется до вызова UseCase.
- [ ] При отказе возвращается корректный `429` или бросается `TooManyRequestsHttpException`.
- [ ] Для JSON-ответа выставлен `Retry-After`.
