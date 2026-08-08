<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Deptrac;

use Qossmic\Deptrac\Contract\OutputFormatter\OutputFormatterInput;
use Qossmic\Deptrac\Contract\OutputFormatter\OutputFormatterInterface;
use Qossmic\Deptrac\Contract\OutputFormatter\OutputInterface;
use Qossmic\Deptrac\Contract\Result\Allowed;
use Qossmic\Deptrac\Contract\Result\CoveredRuleInterface;
use Qossmic\Deptrac\Contract\Result\OutputResult;
use Qossmic\Deptrac\Contract\Result\RuleInterface;
use Qossmic\Deptrac\Contract\Result\SkippedViolation;
use Qossmic\Deptrac\Contract\Result\Uncovered;
use Qossmic\Deptrac\Contract\Result\Violation;
use RuntimeException;

/**
 * Сохраняет полный результат Deptrac для последующей агрегации метрик.
 *
 * Форматтер не определяет модули и не обходит исходники проекта: эти сведения
 * принадлежат конфигурации проекта-потребителя и анализатору классов.
 */
final class MetricsJsonOutputFormatter implements OutputFormatterInterface
{
    public static function getName(): string
    {
        return 'metrics-json';
    }

    public function finish(OutputResult $result, OutputInterface $output, OutputFormatterInput $input): void
    {
        $json = json_encode([
            'schema_version' => '1.0',
            'dependencies' => $this->dependencies($result),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        if ($input->outputPath === null) {
            $output->writeRaw($json);

            return;
        }

        $directory = dirname($input->outputPath);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Cannot create metrics directory: %s', $directory));
        }

        file_put_contents($input->outputPath, $json . "\n");
        $output->writeLineFormatted(sprintf('<info>Metrics dependency report dumped to %s</info>', $input->outputPath));
    }

    /** @return list<array<string, mixed>> */
    private function dependencies(OutputResult $result): array
    {
        $dependencies = [];
        foreach ($result->allRules() as $rule) {
            $dependency = $rule->getDependency();
            $context = $dependency->getContext();
            $item = [
                'source' => $dependency->getDepender()->toString(),
                'target' => $dependency->getDependent()->toString(),
                'status' => $this->status($rule),
                'context' => [
                    'file' => $this->relativePath($context->fileOccurrence->filepath),
                    'line' => $context->fileOccurrence->line,
                    'type' => $context->dependencyType->value,
                ],
            ];
            if ($rule instanceof CoveredRuleInterface) {
                $item['source_layer'] = $rule->getDependerLayer();
                $item['target_layer'] = $rule->getDependentLayer();
            }
            if ($rule instanceof Uncovered) {
                $item['layer'] = $rule->layer;
            }
            $dependencies[] = $item;
        }
        usort($dependencies, static fn (array $left, array $right): int => json_encode($left) <=> json_encode($right));

        return $dependencies;
    }

    private function status(RuleInterface $rule): string
    {
        return match (true) {
            $rule instanceof Allowed => 'allowed',
            $rule instanceof Violation => 'forbidden',
            $rule instanceof SkippedViolation => 'skipped',
            $rule instanceof Uncovered => 'uncovered',
            default => throw new RuntimeException(sprintf('Unsupported Deptrac rule: %s', $rule::class)),
        };
    }

    private function relativePath(string $path): string
    {
        $root = getcwd();
        if ($root === false) {
            return $path;
        }
        $prefix = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        return str_starts_with($path, $prefix) ? substr($path, strlen($prefix)) : $path;
    }
}
