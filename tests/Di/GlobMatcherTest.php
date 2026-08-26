<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Tests\Di;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PrikotovCodingStandard\Di\GlobMatcher;

final class GlobMatcherTest extends TestCase
{
    private GlobMatcher $matcher;

    protected function setUp(): void
    {
        $this->matcher = new GlobMatcher();
    }

    public function testDoubleStarCoversRootLevelFiles(): void
    {
        self::assertTrue($this->matcher->covers('/project/src/FooDto.php', '/project/src/**/*Dto.php'));
    }

    public function testDoubleStarCoversNestedFiles(): void
    {
        self::assertTrue($this->matcher->covers('/project/src/A/B/FooDto.php', '/project/src/**/*Dto.php'));
    }

    public function testDoubleStarCoversZeroDirectoriesInUseCasePatterns(): void
    {
        self::assertTrue(
            $this->matcher->covers(
                '/src/Application/UseCase/Command/CreateOrderCommand.php',
                '/src/Application/UseCase/Command/**/*Command.php',
            ),
        );
    }

    public function testSingleStarDoesNotCrossSegmentBoundary(): void
    {
        self::assertFalse($this->matcher->covers('/project/src/A/FooDto.php', '/project/src/*Dto.php'));
        self::assertTrue($this->matcher->covers('/project/src/FooDto.php', '/project/src/*Dto.php'));
    }

    public function testDirectoryPatternCoversEverythingBelowIt(): void
    {
        self::assertTrue($this->matcher->covers('/project/src/Resource/config/a.php', '/project/src/Resource'));
        self::assertTrue($this->matcher->covers('/project/src/Resource/a.php', '/project/src/Resource/'));
    }

    public function testDirectoryPatternDoesNotCoverSiblingWithCommonPrefix(): void
    {
        self::assertFalse($this->matcher->covers('/project/src/ResourceX/a.php', '/project/src/Resource'));
    }

    public function testBracesExpandToAlternatives(): void
    {
        self::assertTrue($this->matcher->covers('/project/src/Domain/Entity/X.php', '/project/src/{Domain/Entity,Resource}'));
        self::assertTrue($this->matcher->covers('/project/src/Resource/X.php', '/project/src/{Domain/Entity,Resource}'));
        self::assertFalse($this->matcher->covers('/project/src/Domain/X.php', '/project/src/{Domain/Entity,Resource}'));
    }

    public function testTrailingDoubleStarCoversAllFilesBelow(): void
    {
        self::assertTrue($this->matcher->covers('/project/src/A/b.php', '/project/src/**'));
        self::assertFalse($this->matcher->covers('/project/src', '/project/src/**'));
    }

    public function testExactFilePathMatchesWithoutGlobCharacters(): void
    {
        self::assertTrue($this->matcher->covers('/project/src/FooDto.php', '/project/src/FooDto.php'));
        self::assertFalse($this->matcher->covers('/project/src/BarDto.php', '/project/src/FooDto.php'));
    }

    /** @return iterable<string, array{string, string}> */
    public static function nonCoveringPatternProvider(): iterable
    {
        yield 'single listed file does not cover a nested file' => ['/src/Nested/FooDto.php', '/src/FooDto.php'];
        yield 'single directory does not cover files outside it' => ['/src/Application/Other/FooDto.php', '/src/Application/Dto'];
        yield 'one directory level does not cover deeper levels' => ['/src/A/B/FooDto.php', '/src/*/*Dto.php'];
    }

    #[DataProvider('nonCoveringPatternProvider')]
    public function testSelectivePatternsDoNotCoverUnlistedFiles(string $path, string $pattern): void
    {
        self::assertFalse($this->matcher->covers($path, $pattern));
    }
}
