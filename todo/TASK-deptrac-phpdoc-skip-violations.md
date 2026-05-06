---
type: feat
created: 2026-05-06
priority: P2
status: todo
---

# Deptrac: поддержать PHPDoc-скипы нарушений

## Проблема

В проекте-потребителе `TasK` возникла временная архитектурная ситуация: конкретный класс должен явно зафиксировать одно допустимое нарушение `CrossModuleDomainRule`, но не хочется исключать весь файл через `exclude_files` в `depfile.yaml`.

Нужен локальный, читаемый рядом с кодом механизм:

```php
/**
 * @deptrac-skip Task\Common\Module\User\Domain\Repository\User\UserRepositoryInterface
 * @techdebt 2026-05-06: временное межмодульное исключение для ORM-связи AppOptionModel.creator.
 */
final readonly class OptionStorageManagerService
{
}
```

Сейчас такой механизм был прототипирован в проекте-потребителе, но это неправильно: расширения Deptrac должны жить в `prikotov/coding-standard`, чтобы все проекты использовали единый стандарт.

## Что сделать

1. Добавить в пакет Deptrac subscriber (подписчик события) для PHPDoc-тега `@deptrac-skip`.
2. Subscriber должен:
   - читать PHPDoc-теги с depender-класса;
   - принимать FQCN зависимого класса после `@deptrac-skip`;
   - поддержать `*` как wildcard только если это явно сочтём безопасным;
   - превращать matching `Violation` в `SkippedViolation`, чтобы отчёт показывал `Skipped violations`, а не скрывал нарушение полностью.
3. Зарегистрировать subscriber в `config/deptrac/depfile.yaml` рядом с текущими правилами:
   - `ServiceContractDependencyRule`;
   - `CrossModuleDomainRule`.
4. Добавить unit tests (модульные тесты) на:
   - matching FQCN → violation становится skipped;
   - non-matching FQCN → violation остаётся violation;
   - отсутствие тега → violation остаётся violation;
   - несколько `@deptrac-skip` тегов.
5. Обновить документацию `docs/config/deptrac/README*.md`:
   - когда допустим `@deptrac-skip`;
   - обязательна причина через `@techdebt` или ссылку на задачу;
   - скип не должен использоваться как способ обходить архитектуру без ревью.

## Прототип решения

Прототип из `TasK` можно перенести и адаптировать в пакет:

```php
<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Deptrac;

use DEPTRAC_INTERNAL\Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Qossmic\Deptrac\Contract\Analyser\ProcessEvent;
use Qossmic\Deptrac\Contract\Ast\TaggedTokenReferenceInterface;
use Qossmic\Deptrac\Contract\Result\SkippedViolation;
use Qossmic\Deptrac\Contract\Result\Violation;

final class PhpDocSkipViolationSubscriber implements EventSubscriberInterface
{
    private const string TAG = '@deptrac-skip';

    public static function getSubscribedEvents(): array
    {
        return [
            // CrossModuleDomainRule/ServiceContractDependencyRule run at priority 3.
            // Convert matching violations before Deptrac core handlers can stop propagation.
            ProcessEvent::class => ['onProcessEvent', 2],
        ];
    }

    public function onProcessEvent(ProcessEvent $event): void
    {
        if (!$event->dependerReference instanceof TaggedTokenReferenceInterface) {
            return;
        }

        if (!$this->shouldSkip($event)) {
            return;
        }

        foreach ($event->getResult()->rules()[Violation::class] ?? [] as $violation) {
            if (!$violation instanceof Violation) {
                continue;
            }

            if ($violation->getDependency() !== $event->dependency) {
                continue;
            }

            $event->getResult()->removeRule($violation);
            $event->getResult()->addRule(new SkippedViolation(
                $violation->getDependency(),
                $violation->getDependerLayer(),
                $violation->getDependentLayer(),
            ));
        }
    }

    private function shouldSkip(ProcessEvent $event): bool
    {
        $dependent = $event->dependentReference->getToken()->toString();

        foreach ($this->skipTargets($event->dependerReference) as $target) {
            if ($target === '*' || $target === $dependent) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function skipTargets(TaggedTokenReferenceInterface $reference): array
    {
        $tagLines = $reference->getTagLines(self::TAG) ?? [];
        $targets = [];

        foreach ($tagLines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $parts = preg_split('/[\s,]+/', $line) ?: [];
            foreach ($parts as $part) {
                $target = trim($part, " \t\n\r\0\x0B'\"");
                if ($target === '' || !($target === '*' || str_contains($target, '\\'))) {
                    continue;
                }

                $targets[] = $target;
            }
        }

        return array_values(array_unique($targets));
    }
}
```

## Acceptance Criteria

- [ ] `@deptrac-skip Some\Class` работает из PHPDoc depender-класса.
- [ ] Нарушение отображается как `Skipped violations`, а не исчезает полностью.
- [ ] Есть тесты на positive/negative scenarios.
- [ ] Документация объясняет, что это временный и ревьюируемый механизм.
- [ ] `composer check` проходит успешно.

## Связанный контекст

- Проект-потребитель: `~/MyProjects/TasK/Development`
- Кейс: `OptionStorageManagerService` временно зависит от `UserRepositoryInterface` из-за ORM-связи `AppOptionModel.creator`.
