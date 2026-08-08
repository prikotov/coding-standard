<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Tests\Deptrac;

use PHPUnit\Framework\TestCase;
use PrikotovCodingStandard\Deptrac\MetricsJsonOutputFormatter;
use Qossmic\Deptrac\Contract\Analyser\AnalysisResult;
use Qossmic\Deptrac\Contract\Ast\DependencyContext;
use Qossmic\Deptrac\Contract\Ast\DependencyType;
use Qossmic\Deptrac\Contract\Ast\FileOccurrence;
use Qossmic\Deptrac\Contract\OutputFormatter\OutputFormatterInput;
use Qossmic\Deptrac\Contract\OutputFormatter\OutputFormatterInterface;
use Qossmic\Deptrac\Contract\OutputFormatter\OutputInterface;
use Qossmic\Deptrac\Contract\OutputFormatter\OutputStyleInterface;
use Qossmic\Deptrac\Contract\Result\Allowed;
use Qossmic\Deptrac\Contract\Result\OutputResult;
use Qossmic\Deptrac\Core\Ast\AstMap\ClassLike\ClassLikeToken;
use Qossmic\Deptrac\Core\Dependency\Dependency;
use RuntimeException;

final class MetricsJsonOutputFormatterTest extends TestCase
{
    public function testWritesCompleteAllowedDependencyWithLayersAndContext(): void
    {
        $analysis = new AnalysisResult();
        $analysis->addRule(new Allowed(
            new Dependency(
                ClassLikeToken::fromFQCN('App\\Source'),
                ClassLikeToken::fromFQCN('App\\Target'),
                new DependencyContext(
                    new FileOccurrence(getcwd() . '/src/Source.php', 42),
                    DependencyType::PARAMETER,
                ),
            ),
            'SourceModule',
            'TargetModule',
        ));
        $output = new MetricsOutput();

        (new MetricsJsonOutputFormatter())->finish(
            OutputResult::fromAnalysisResult($analysis),
            $output,
            new OutputFormatterInput(null, false, false, false),
        );

        self::assertSame('metrics-json', MetricsJsonOutputFormatter::getName());
        self::assertSame([
            'schema_version' => '1.0',
            'dependencies' => [[
                'source' => 'App\\Source',
                'target' => 'App\\Target',
                'status' => 'allowed',
                'context' => ['file' => 'src/Source.php', 'line' => 42, 'type' => 'parameter'],
                'source_layer' => 'SourceModule',
                'target_layer' => 'TargetModule',
            ]],
        ], json_decode($output->content, true, flags: JSON_THROW_ON_ERROR));
    }
}

final class MetricsOutput implements OutputInterface
{
    public string $content = '';

    public function writeFormatted(string $message): void
    {
        $this->content .= $message;
    }

    public function writeLineFormatted(string|array $message): void
    {
    }

    public function writeRaw(string $message): void
    {
        $this->content .= $message;
    }

    public function getStyle(): OutputStyleInterface
    {
        throw new RuntimeException('Style is not used by this formatter test.');
    }

    public function isVerbose(): bool
    {
        return false;
    }

    public function isDebug(): bool
    {
        return false;
    }
}
