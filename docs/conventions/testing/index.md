# Testing

Система тестирования: виды тестов, инструменты, правила написания и примеры.

## Обзор системы тестирования

Многоуровневая система тестирования:

- **Unit-тесты** — проверка бизнес-логики без внешних зависимостей
- **Integration-тесты** — проверка взаимодействия слоёв и инфраструктуры
- **Функциональные тесты** — проверка работы приложения через публичные интерфейсы
- **E2E-тесты** — проверка сценариев через публичные интерфейсы

Все тесты выполняются в окружении `test`, конфигурация из `config/packages/test/`.

## Виды тестов

### Unit-тесты

Проверка бизнес-логики в изоляции от внешних зависимостей (БД, файловая система, внешние API).

- **Расположение:** `tests/Unit/` (повторяет структуру `src/`)
- Наследуются от `PHPUnit\Framework\TestCase`
- Не используют реальную БД или внешние сервисы
- Используют моки (mocks) и стабы (stubs) для зависимостей
- Быстрые и изолированные

**Пример:**

```php
<?php

declare(strict_types=1);

namespace Common\Test\Unit\Module\Source\Domain\Entity;

use Common\Module\Source\Domain\Entity\SourceModel;
use PHPUnit\Framework\TestCase;

final class SourceModelTest extends TestCase
{
    public function testConstructorRejectsEmptyFilename(): void
    {
        $this->expectException(EmptyFilenameException::class);

        new SourceModel(/* ... */, filename: '');
    }
}
```

### Integration-тесты

Проверка взаимодействия слоёв и инфраструктуры с использованием тестовой БД и моков для внешних API.

- **Расположение:** `tests/Integration/` (повторяет структуру `src/`)
- **Обязательное наследование** от `Common\Component\Test\KernelTestCase`
- Используют реальную тестовую БД (PostgreSQL)
- Инициализируют ядро Symfony через `self::bootKernel()`
- Ядро перезапускается перед каждым тестом для изоляции

**Пример:**

```php
<?php

declare(strict_types=1);

namespace Common\Test\Integration\Module\User\Application\UseCase\Command\User\Register;

use Common\Component\Test\KernelTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;

final class RegisterCommandHandlerTest extends KernelTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->updateSchema($entityManager->getMetadataFactory()->getAllMetadata());
    }

    public function testRegistrationCreatesInactiveUser(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        /** @var RegisterCommandHandler $handler */
        $handler = $container->get(RegisterCommandHandler::class);

        $handler(new RegisterCommand(
            email: 'bar@example.com',
            username: 'bar',
            /* ... */
        ));

        $userRepository = $container->get(UserRepositoryInterface::class);
        $user = $userRepository->getOneByCriteria(new UserFindCriteria(email: 'bar@example.com'));

        self::assertNotNull($user);
    }
}
```

## Фикстуры (DataFixtures)

Фикстуры — классы для наполнения тестовой БД начальными данными.

### Правила создания

- Наследоваться от `Doctrine\Bundle\FixturesBundle\Fixture`
- Реализовывать `load(ObjectManager $manager)`
- Использовать `$manager->persist()` + `$manager->flush()`
- Определять зависимости через `DependentFixtureInterface`

### Использование ссылок (references)

```php
// Добавляем ссылку
$this->addReference('test-user', $user);

// Получаем в зависимой фикстуре
$user = $this->getReference('test-user', UserModel::class);
```

### Константы для ссылок

```php
final class UserFixtures extends Fixture
{
    public const string TEST_USER_REFERENCE = 'test-user';

    // ...
}
```

### Зависимости между фикстурами

```php
use Doctrine\Common\DataFixtures\DependentFixtureInterface;

final class UserRoleFixture extends Fixture implements DependentFixtureInterface
{
    #[Override]
    public function getDependencies(): array
    {
        return [UserFixtures::class];
    }

    #[Override]
    public function load(ObjectManager $manager): void
    {
        $user = $this->getReference(UserFixtures::TEST_USER_REFERENCE, UserModel::class);
        $manager->persist(new UserRoleModel($user, RoleEnum::admin));
        $manager->flush();
    }
}
```

### Рекомендации

- Одна фикстура — один домен (пользователи, проекты, роли)
- Константы для ссылок — без опечаток и проще рефакторинг
- Методы-хелперы для повторяющегося кода (`createUser()`, `owner()`)
- Циклы для множества похожих сущностей
- Только нужные для тестов данные, не избыточные

## E2E-тесты

E2E-тесты проверяют полные сценарии через публичные интерфейсы.

- **Расположение:** `apps/*/tests/E2E/`
- **Web E2E:** Symfony Panther (реальный браузер, JavaScript, Turbo, Stimulus)
- **API E2E:** `ApiTestCase` (REST API без накладных расходов на браузер)

Подробная документация: [`e2e.md`](./e2e.md)

## Инструменты тестирования

### PHPUnit

Основной фреймворк. Конфигурация: [`phpunit.xml.dist`](../../examples/phpunit.xml.dist)

```bash
make tests                    # Все тесты
make tests-unit               # Unit-тесты
make tests-integration        # Integration-тесты
make tests-integration-fixtures  # С фикстурами
make coverage                 # С покрытием
```

### Psalm

Статический анализ типов. Уровень ошибок: `2`. Конфигурация: [`psalm.xml`](../../examples/psalm.xml)

```bash
make psalm
```

### Deptrac

Анализ архитектуры (слои, модули). Конфигурация: [`depfile.yaml`](../../examples/depfile.yaml)

```bash
make deptrac
```

### PHP_CodeSniffer

Проверка стиля (PSR-12 + дополнительные правила). Конфигурация: [`phpcs.xml.dist`](../../examples/phpcs.xml.dist)

```bash
make phpcs
```

### Composer audit

```bash
make audit
```

## Правила написания тестов

### Общие правила

- Любое изменение кода сопровождается тестами соответствующего уровня
- Новый код в Domain/Application — покрытие минимум 80%
- Integration/функциональные тесты наследуются от `Common\Component\Test\KernelTestCase`
- Ядро в integration-тестах через `self::bootKernel()`
- Без реальных внешних сервисов и секретных данных в тестах

### Структура: AAA (Arrange-Act-Assert)

```php
public function testInvokeValidDataReturnsUserUuid(): void
{
    // Arrange
    $repository = $this->createMock(UserRepositoryInterface::class);
    $repository->method('exists')->willReturn(false);
    $handler = new CreateCommandHandler($repository, ...);

    // Act
    $result = $handler(new CreateCommand(email: 'test@example.com', /* ... */));

    // Assert
    self::assertInstanceOf(IdDto::class, $result);
}
```

Правила:
- Разделяйте секции пустой строкой
- Act — одна строка (или минимум)
- Assert группируйте по смыслу

### BDD-стиль именования

```
test{WhatIsBeingTested}{Scenario}{ExpectedResult}
```

Примеры: `testRegistrationWithoutInviteCreatesInactiveUser`, `testApiRequestInvalidJsonReturns400BadRequest`

### Покрытие кода

- Минимум 80% для нового кода в Domain/Application
- Покрытие по затронутым участкам (не глобально)
- `make coverage` для HTML-отчёта

## Команды для запуска проверок

### Полная проверка (CI)

```bash
make check
```

Включает: `install` → `tests` → `phpmd-fast` → `deptrac` → `psalm` → `phpcs`

### Отдельные команды

```bash
make tests-unit               # Unit-тесты
make tests-integration        # Integration-тесты
make tests-e2e                # Все E2E тесты
make tests-e2e-web            # Web E2E (Panther)
make tests-e2e-api            # API E2E
make psalm                    # Статический анализ
make deptrac                  # Анализ архитектуры
make phpcs                    # Проверка стиля
make phpmd                    # PHP Mess Detector
make coverage                 # Покрытие
make e2e-up                   # Поднять E2E окружение
```

## Структура директории tests/

```
tests/
├── bootstrap.php              # Bootstrap PHPUnit
├── _output/                   # Кэш и отчёты
├── Unit/                      # Unit-тесты (повторяет src/)
├── Integration/               # Integration-тесты (повторяет src/)
├── Stub/                      # Заглушки и фикстуры
└── Support/                   # Вспомогательные классы
```

## Конфигурационные файлы

| Файл | Описание |
|------|----------|
| [`phpunit.xml.dist`](../../examples/phpunit.xml.dist) | Конфигурация PHPUnit |
| [`psalm.xml`](../../examples/psalm.xml) | Конфигурация Psalm |
| [`phpcs.xml.dist`](../../examples/phpcs.xml.dist) | Конфигурация PHP_CodeSniffer |
| [`phpmd.xml`](../../examples/phpmd.xml) | Конфигурация PHP Mess Detector |
| [`Makefile`](../../examples/Makefile) | Команды для проверок |
| [`depfile.yaml`](../../examples/depfile.yaml) | Конфигурация Deptrac |

## Дополнительные ресурсы

- [Symfony Testing](https://symfony.com/doc/current/testing.html)
- [PHPUnit](https://phpunit.de/documentation.html)
- [Psalm](https://psalm.dev/docs/)
- [PHP_CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer)

## Связанная документация

- [`e2e.md`](./e2e.md) — детальное руководство по E2E тестированию
- [`AGENTS.md`](../AGENTS.md) — общие правила проекта
