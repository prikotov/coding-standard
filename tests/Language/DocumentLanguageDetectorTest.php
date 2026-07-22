<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Tests\Language;

use PHPUnit\Framework\TestCase;
use PrikotovCodingStandard\Language\DocumentLanguageDetector;

final class DocumentLanguageDetectorTest extends TestCase
{
    public function testDefaultsToRussianWithoutAnyMarker(): void
    {
        $detector = new DocumentLanguageDetector();

        $d = $detector->detect('docs/conventions/dto.md', "# DTO\n\nТекст.");

        self::assertSame('ru', $d->language);
        self::assertFalse($d->conflict);
    }

    public function testDetectsLanguageFromFrontMatter(): void
    {
        $detector = new DocumentLanguageDetector();

        $content = "---\nname: Glossary\nlang: en\ntype: rule\n---\nEnglish text.";

        $d = $detector->detect('docs/glossary.md', $content);

        self::assertSame('en', $d->language);
        self::assertFalse($d->conflict);
    }

    public function testDetectsLanguageFromFilenameSuffix(): void
    {
        $detector = new DocumentLanguageDetector();

        $d = $detector->detect('docs/glossary.en.md', "# Glossary\n\nText.");

        self::assertSame('en', $d->language);
        self::assertFalse($d->conflict);
    }

    public function testSameLanguageInFrontMatterAndFilenameIsNotConflict(): void
    {
        $detector = new DocumentLanguageDetector();

        $content = "---\nname: Doc\nlang: en\n---\nEnglish.";

        // Оба маркера указывают en — конфликта нет.
        $d = $detector->detect('docs/doc.en.md', $content);

        self::assertSame('en', $d->language);
        self::assertFalse($d->conflict);
    }

    public function testConflictingMarkersReportedAsError(): void
    {
        $detector = new DocumentLanguageDetector();

        $content = "---\nname: Doc\nlang: ru\n---\nРусский.";

        // front matter ru, filename .en — конфликт маркеров языка.
        $d = $detector->detect('docs/doc.en.md', $content);

        self::assertTrue($d->conflict);
        self::assertSame('ru', $d->fromFrontMatter);
        self::assertSame('en', $d->fromFilename);
    }

    public function testFilenameWithoutLangSuffixDefaultsToRussian(): void
    {
        $detector = new DocumentLanguageDetector();

        $d = $detector->detect('docs/ops/index.md', "Текст.");

        self::assertSame('ru', $d->language);
    }

    public function testIgnoresNonTwoLetterSuffixes(): void
    {
        $detector = new DocumentLanguageDetector();

        // tasks.md — «tasks» не код языка (4 буквы).
        $d = $detector->detect('docs/tasks.md', "Текст.");

        self::assertSame('ru', $d->language);
    }

    public function testSupportsLocaleWithRegion(): void
    {
        $detector = new DocumentLanguageDetector();

        $d = $detector->detect('docs/readme.en-US.md', "# Readme");

        self::assertSame('en-us', $d->language);
    }

    public function testExplicitRussianInFrontMatter(): void
    {
        $detector = new DocumentLanguageDetector();

        $content = "---\nname: Док\nlang: ru\ntype: rule\n---\nРусский.";

        $d = $detector->detect('docs/doc.md', $content);

        self::assertSame('ru', $d->language);
    }

    public function testQuotedLangValue(): void
    {
        $detector = new DocumentLanguageDetector();

        $content = "---\nname: Doc\nlang: \"en\"\n---\nText.";

        $d = $detector->detect('docs/doc.md', $content);

        self::assertSame('en', $d->language);
    }
}
