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

        self::assertSame('ru', $detector->detect('docs/conventions/dto.md', "# DTO\n\nТекст."));
    }

    public function testDetectsLanguageFromFrontMatter(): void
    {
        $detector = new DocumentLanguageDetector();

        $content = "---\nname: Glossary\nlang: en\ntype: rule\n---\nEnglish text.";

        self::assertSame('en', $detector->detect('docs/glossary.md', $content));
    }

    public function testFrontMatterTakesPrecedenceOverFilename(): void
    {
        $detector = new DocumentLanguageDetector();

        $content = "---\nname: Doc\nlang: ru\n---\nРусский текст.";

        // Файл имеет .en. в имени, но front matter говорит ru — приоритет за front matter.
        self::assertSame('ru', $detector->detect('docs/doc.en.md', $content));
    }

    public function testDetectsLanguageFromFilenameSuffix(): void
    {
        $detector = new DocumentLanguageDetector();

        self::assertSame('en', $detector->detect('docs/glossary.en.md', "# Glossary\n\nText."));
    }

    public function testFilenameWithoutLangSuffixDefaultsToRussian(): void
    {
        $detector = new DocumentLanguageDetector();

        // index.md — нет кода языка перед .md.
        self::assertSame('ru', $detector->detect('docs/ops/index.md', "Текст."));
    }

    public function testIgnoresNonTwoLetterSuffixes(): void
    {
        $detector = new DocumentLanguageDetector();

        // tasks.md — «tasks» не код языка (4 буквы).
        self::assertSame('ru', $detector->detect('docs/tasks.md', "Текст."));
    }

    public function testSupportsLocaleWithRegion(): void
    {
        $detector = new DocumentLanguageDetector();

        self::assertSame('en-us', $detector->detect('docs/readme.en-US.md', "# Readme"));
    }

    public function testExplicitRussianInFrontMatter(): void
    {
        $detector = new DocumentLanguageDetector();

        $content = "---\nname: Док\nlang: ru\ntype: rule\n---\nРусский.";

        self::assertSame('ru', $detector->detect('docs/doc.md', $content));
    }

    public function testQuotedLangValue(): void
    {
        $detector = new DocumentLanguageDetector();

        $content = "---\nname: Doc\nlang: \"en\"\n---\nText.";

        self::assertSame('en', $detector->detect('docs/doc.md', $content));
    }
}
