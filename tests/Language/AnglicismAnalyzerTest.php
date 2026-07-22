<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Tests\Language;

use PHPUnit\Framework\TestCase;
use PrikotovCodingStandard\Language\AnglicismAnalyzer;
use PrikotovCodingStandard\Language\MarkdownTextExtractor;

final class AnglicismAnalyzerTest extends TestCase
{
    public function testPureRussianTextHasZeroAnglicisms(): void
    {
        $analyzer = new AnglicismAnalyzer();

        $result = $analyzer->analyze('Это обычный русский текст без англицизмов.');

        self::assertSame(0, $result->anglicismWords);
        self::assertSame(0.0, $result->ratio);
        self::assertSame([], $result->suspiciousPhrases);
    }

    public function testDetectsEnglishPhraseInRussianProse(): void
    {
        $analyzer = new AnglicismAnalyzer();

        $result = $analyzer->analyze('Мы сохраняем persisted rows в базу данных.');

        self::assertGreaterThan(0, $result->anglicismWords);
        self::assertContains('persisted rows', $result->suspiciousPhrases);
    }

    public function testDetectsMultipleEnglishPhrases(): void
    {
        $analyzer = new AnglicismAnalyzer();

        $result = $analyzer->analyze("persisted rows — это одно.\nRead-only facts — другое.");

        self::assertContains('persisted rows', $result->suspiciousPhrases);
        self::assertContains('Read-only facts', $result->suspiciousPhrases);
    }

    public function testAllowlistedTermsAreNotAnglicisms(): void
    {
        $analyzer = new AnglicismAnalyzer();

        // Symfony и DTO — в allowlist.
        $result = $analyzer->analyze('Используем Symfony и DTO в проекте.');

        self::assertSame(0, $result->anglicismWords);
    }

    public function testExtraAllowlistSuppressesTerms(): void
    {
        $analyzer = new AnglicismAnalyzer(['sniff', 'custom']);

        $result = $analyzer->analyze('Добавили custom sniff в стандарт.');

        self::assertSame(0, $result->anglicismWords);
    }

    public function testEnglishOnlyLineIsNotSuspiciousPhrase(): void
    {
        $analyzer = new AnglicismAnalyzer();

        // Строка целиком английская — не «mixed», не англицизм в русском тексте.
        $result = $analyzer->analyze("Symfony Panther PHPUnit PHPStan\nРусская строка.");

        self::assertSame([], $result->suspiciousPhrases);
    }

    public function testRatioCalculation(): void
    {
        $analyzer = new AnglicismAnalyzer();

        // 5 слов: «текст», «persisted», «rows», «база», «данных».
        // Англицизмы (не allowlist): persisted, rows = 2.
        $result = $analyzer->analyze('текст persisted rows база данных');

        self::assertSame(5, $result->totalWords);
        self::assertSame(2, $result->anglicismWords);
        self::assertSame(0.4, $result->ratio);
    }

    public function testSingleEnglishWordCountsAsAnglicism(): void
    {
        $analyzer = new AnglicismAnalyzer();

        // Одиночное «allowlist» в русском тексте — англицизм, входит в ratio.
        // (Термины в backticks исключаются экстрактором до analyze — см. MarkdownTextExtractorTest.)
        $result = $analyzer->analyze('Слова вне allowlist не входят в метрику.');

        self::assertSame(1, $result->anglicismWords);
        self::assertContains('allowlist', $result->sampleWords);
    }

    public function testHyphenatedWordCountedAsOne(): void
    {
        $analyzer = new AnglicismAnalyzer();

        // «read-only» — один токен с дефисом.
        $result = $analyzer->analyze('используем read-only режим');

        self::assertSame(1, $result->anglicismWords);
    }

    public function testInlineCodeTermExcludedViaExtractorPipeline(): void
    {
        // Сценарий из конвенции: `ratio` в backticks — inline code, исключается
        // экстрактором; одиночный англицизм allowlist — ловится.
        $extractor = new MarkdownTextExtractor();
        $analyzer = new AnglicismAnalyzer();

        $prose = $extractor->extract('Слова вне allowlist не входят в `ratio`.');
        $result = $analyzer->analyze($prose);

        self::assertSame(1, $result->anglicismWords);
        self::assertContains('allowlist', $result->sampleWords);
    }

    public function testEmptyText(): void
    {
        $analyzer = new AnglicismAnalyzer();

        $result = $analyzer->analyze('');

        self::assertSame(0, $result->totalWords);
        self::assertSame(0.0, $result->ratio);
        self::assertSame([], $result->suspiciousPhrases);
    }

    public function testDddLayerTermsAreAllowed(): void
    {
        $analyzer = new AnglicismAnalyzer();

        // Domain, Service, Repository — названия слоёв/паттернов в allowlist.
        $result = $analyzer->analyze('Domain Service возвращает Repository.');

        self::assertSame(0, $result->anglicismWords);
        self::assertSame([], $result->suspiciousPhrases);
    }
}
