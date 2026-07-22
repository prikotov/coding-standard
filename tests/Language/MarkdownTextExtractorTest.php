<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Tests\Language;

use PHPUnit\Framework\TestCase;
use PrikotovCodingStandard\Language\MarkdownTextExtractor;

final class MarkdownTextExtractorTest extends TestCase
{
    public function testStripsYamlFrontMatter(): void
    {
        $md = "---\nname: Test\ntype: rule\n---\nТело документа.";

        $extractor = new MarkdownTextExtractor();

        self::assertSame('Тело документа.', $extractor->extract($md));
    }

    public function testStripsFencedCodeBlocks(): void
    {
        $md = "Проза.\n\n```\nenglish words here\n```\n\nЕщё проза.";

        $extractor = new MarkdownTextExtractor();

        self::assertSame('Проза.' . "\n\n\n\nЕщё проза.", $extractor->extract($md));
    }

    public function testStripsTildeFencedCodeBlocks(): void
    {
        $md = "Проза.\n\n~~~\nenglish words\n~~~\n\nЕщё проза.";

        $extractor = new MarkdownTextExtractor();

        self::assertStringNotContainsString('english', $extractor->extract($md));
    }

    public function testStripsInlineCode(): void
    {
        $md = 'Используем `someFunction` в коде.';

        $extractor = new MarkdownTextExtractor();

        self::assertSame('Используем  в коде.', $extractor->extract($md));
    }

    public function testStripsUrls(): void
    {
        $md = 'См. https://example.com/page для деталей.';

        $extractor = new MarkdownTextExtractor();

        self::assertSame('См.  для деталей.', $extractor->extract($md));
    }

    public function testStripsNamespaces(): void
    {
        $md = 'Класс \App\Module\Domain\Foo используется.';

        $extractor = new MarkdownTextExtractor();

        self::assertStringNotContainsString('Module', $extractor->extract($md));
        self::assertStringContainsString('Класс', $extractor->extract($md));
    }

    public function testStripsTaskIds(): void
    {
        $md = 'Задача TASK-feat-some-validator в работе.';

        $extractor = new MarkdownTextExtractor();

        self::assertSame('Задача  в работе.', $extractor->extract($md));
    }

    public function testStripsFilenames(): void
    {
        $md = 'Конфиг в config.php и docs/AGENTS.md.';

        $extractor = new MarkdownTextExtractor();

        self::assertStringNotContainsString('AGENTS', $extractor->extract($md));
    }

    public function testStripsPlaceholders(): void
    {
        $md = 'Путь {ProjectName}\Common\{ModuleName}.';

        $extractor = new MarkdownTextExtractor();

        self::assertStringNotContainsString('ProjectName', $extractor->extract($md));
        self::assertStringNotContainsString('ModuleName', $extractor->extract($md));
    }

    public function testStripsParenthesisedLatinTermTranslations(): void
    {
        $md = 'Обработчик команд (Command Handler) возвращает DTO.';

        $extractor = new MarkdownTextExtractor();

        // (Command Handler) — перевод термина, удаляется целиком.
        self::assertStringNotContainsString('Command Handler', $extractor->extract($md));
        self::assertStringNotContainsString('Handler', $extractor->extract($md));
        self::assertStringContainsString('Обработчик команд', $extractor->extract($md));
    }

    public function testKeepsCyrillicParentheses(): void
    {
        $md = 'Термин (на русском) остаётся.';

        $extractor = new MarkdownTextExtractor();

        self::assertSame('Термин (на русском) остаётся.', $extractor->extract($md));
    }

    public function testStripsMarkdownHeadings(): void
    {
        $md = "# Title\n\n## Section\n\nПроза документа.";

        $extractor = new MarkdownTextExtractor();

        self::assertSame("\n\n\n\nПроза документа.", $extractor->extract($md));
    }

    public function testStripsReferences(): void
    {
        $md = 'См. issue #65 и PR #66, упоминание @user.';

        $extractor = new MarkdownTextExtractor();

        $result = $extractor->extract($md);
        self::assertStringNotContainsString('#65', $result);
        self::assertStringNotContainsString('@user', $result);
        self::assertStringContainsString('issue', $result);
    }
}
