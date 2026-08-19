<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Tests\Metrics;

use PHPUnit\Framework\TestCase;
use PrikotovCodingStandard\Metrics\DirectoryRemover;

final class MetricsCollectorTest extends TestCase
{
    public function testCollectsClassesAndMethodComplexityFromPhpSource(): void
    {
        $directory = sys_get_temp_dir() . '/metrics-collector-' . uniqid();
        mkdir($directory);
        $source = $directory . '/Sample.php';
        $output = $directory . '/report.json';
        file_put_contents($source, <<<'PHP_SOURCE'
<?php
namespace App;
final class Sample {
    private string $value;
    public function decide(bool $condition): string {
        if ($condition) { return $this->value; }
        return 'no';
    }
}
PHP_SOURCE);

        exec(sprintf('%s bin/metrics-collect --source=%s --output=%s', escapeshellarg(PHP_BINARY), escapeshellarg($directory), escapeshellarg($output)), $lines, $code);
        $report = json_decode((string) file_get_contents($output), true, flags: JSON_THROW_ON_ERROR);
        unlink($source);
        unlink($output);
        rmdir($directory);

        self::assertSame(0, $code);
        self::assertSame('App\\Sample', $report['classes'][0]['name']);
        self::assertSame(1, $report['classes'][0]['metrics']['propertyCount']);
        self::assertSame(2, $report['functions'][0]['metrics']['cc']);
    }

    public function testDetectsCommandHandlerEventDispatchFlags(): void
    {
        $directory = sys_get_temp_dir() . '/metrics-collector-' . uniqid();
        $output = $directory . '/report.json';
        mkdir($directory . '/Application/UseCase/Command/Create', 0777, true);
        mkdir($directory . '/Application/UseCase/Command/Notify', 0777, true);
        file_put_contents($directory . '/Application/UseCase/Command/Create/CreateCommandHandler.php', <<<'PHP_SOURCE'
<?php
final class CreateCommandHandler {
    public function __invoke(): void {
        $this->repository->save($this->model);
        $this->persistenceManager->flush();
    }
}
PHP_SOURCE);
        file_put_contents($directory . '/Application/UseCase/Command/Notify/NotifyCommandHandler.php', <<<'PHP_SOURCE'
<?php
final class NotifyCommandHandler {
    public function __invoke(): void {
        $this->repository->save($this->model);
        $this->eventBus->dispatch(new NotifiedEvent());
    }
}
PHP_SOURCE);
        file_put_contents($directory . '/PlainService.php', <<<'PHP_SOURCE'
<?php
final class PlainService {
    public function run(): void {}
}
PHP_SOURCE);

        exec(sprintf('%s bin/metrics-collect --source=%s --output=%s', escapeshellarg(PHP_BINARY), escapeshellarg($directory), escapeshellarg($output)), $lines, $code);
        $report = json_decode((string) file_get_contents($output), true, flags: JSON_THROW_ON_ERROR);
        (new DirectoryRemover())->remove($directory);

        $handlers = [];
        foreach ($report['classes'] as $class) {
            $handlers[$class['name']] = $class['metrics']['commandHandler'];
        }

        self::assertSame(0, $code);
        self::assertSame(['hasPersistenceCalls' => true, 'hasEventDispatchCalls' => false], $handlers['CreateCommandHandler']);
        self::assertSame(['hasPersistenceCalls' => true, 'hasEventDispatchCalls' => true], $handlers['NotifyCommandHandler']);
        self::assertNull($handlers['PlainService']);
    }
}
