# Смоук-команды (Smoke Commands)

## Определение

**Смоук-команды (Smoke Commands)** — это безопасные консольные команды для ручной проверки, preview и точечных
операционных сценариев. Они помогают быстро подтвердить, что конкретный flow, интеграция или шаблон работают в
реальной среде. Базовый CLI-механизм строится на [Symfony Console](https://symfony.com/doc/current/components/console.html).

Смоук-команда не является частью штатного продуктового поведения, не предназначена для массового изменения данных и не
заменяет `Fix`, миграции или cron-команды.

## Общие правила

- Смоук-команда должна быть безопасной по умолчанию.
- Смоук-команда создаётся для ручной проверки, preview или узкого operational check.
- Команда не должна скрыто запускать массовые побочные эффекты.
- Команда должна явно показывать, что именно она проверяет и какой контекст использует.
- Команда должна быть пригодна для ручного запуска разработчиком, QA или support без чтения внутреннего кода.
- Если сценарий меняет данные массово, исправляет инварианты или требует `dry-run` + `--execute`, нужно использовать
  [Fixes](fixes.md), а не `Smoke`.
- Если команда предполагается для регулярной эксплуатации, cron или фоновой автоматизации, её не следует относить к
  `Smoke`.

## Зависимости

- Для смоук-команд действуют общие правила для [консольных команд Presentation-слоя](../layers/presentation/console-command.md).
- Дополнительно команда не должна зависеть от реализации сценариев массового data-fix или recovery.
- Любой побочный эффект должен быть узким, явным и ожидаемым из CLI-контракта команды.

## Расположение

Смоук-команды размещаются в подпапке `Smoke/` внутри console module:

```
apps/console/src/Module/<ModuleName>/Command/Smoke/<ActionName>Command.php
```

Пример:

```
apps/console/src/Module/Notification/Command/Smoke/SendWelcomeEmailCommand.php
```

Подпапка `Smoke/` обязательна, если команда создаётся именно для preview, ручной проверки или точечного operational
check. Это даёт читателю явный сигнал: команда не является регулярным рабочим инструментом уровня `app:user:create`
или `billing:sync`.

## Как используем

- Используем смоук-команду, когда нужен воспроизводимый CLI entry point для проверки результата задачи.
- Команда валидирует все входные аргументы и опции до вызова Application-слоя.
- Команда принимает только явно заданный target или использует безопасный fallback.
- Команда выводит итоговый контекст проверки: например адрес получателя, locale, выбранный plan, режим preview.
- Команда должна быть детерминированной и человекочитаемой.
- Если действие асинхронное, это должно быть явно сказано в CLI output.
- Для потенциально дорогих или опасных действий нужны explicit-опции (`--execute`, `--force`, target argument), а не
  скрытое поведение.

Допустимые сценарии:

- отправить test/preview email на явно указанный адрес;
- проверить, что transport, шаблон или queue работают;
- выполнить ручной probe на ограниченном наборе данных или одном ресурсе;
- показать resolved context, который иначе собирается внутри product flow.

Недопустимые сценарии:

- массовая правка данных;
- восстановление данных после инцидента;
- backfill, reprocessing или миграция данных;
- регулярный cron или operational workflow.

## Пример

Упрощённый пример смоук-команды для preview welcome email:

```php
<?php

declare(strict_types=1);

namespace Console\Module\Notification\Command\Smoke;

use Common\Module\User\Application\Service\Email\WelcomeEmailServiceInterface;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'notification:send-welcome-email',
    description: 'Отправить preview welcome email на указанный адрес.',
)]
final class SendWelcomeEmailCommand extends Command
{
    public function __construct(
        private readonly WelcomeEmailServiceInterface $welcomeEmailService,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'Кому отправить preview письмо.');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->success('Welcome email enqueued.');
        $io->note('Delivery is asynchronous.');

        return Command::SUCCESS;
    }
}
```

## Чек-лист для ревью кода

- [ ] Команда действительно относится к smoke/manual-check сценарию.
- [ ] Файл расположен в `Command/Smoke/`.
- [ ] Входные параметры валидируются до вызова Application.
- [ ] Нет скрытых массовых побочных эффектов.
- [ ] Вывод команды достаточен для ручной диагностики.
- [ ] Для асинхронного поведения есть явное сообщение в CLI output.
