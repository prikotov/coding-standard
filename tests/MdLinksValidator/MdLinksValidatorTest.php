<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Tests\MdLinksValidator;

use PHPUnit\Framework\TestCase;

/**
 * Tests for bin/validate-md-links.php.
 *
 * Runs the script against fixture files and checks exit code and output.
 */
final class MdLinksValidatorTest extends TestCase
{
    private const FIXTURE_DIR = __DIR__ . '/../fixtures/md-links';
    private const SCRIPT = __DIR__ . '/../../bin/validate-md-links.php';

    private static string $fixtureOutput;
    private static int $fixtureExitCode;

    public static function setUpBeforeClass(): void
    {
        // Run once for all fixture-based tests
        $result = self::runScript([self::FIXTURE_DIR]);
        self::$fixtureOutput = $result['output'];
        self::$fixtureExitCode = $result['exitCode'];
    }

    public function testDetectsBrokenLinksExitsNonZero(): void
    {
        $this->assertSame(1, self::$fixtureExitCode,
            'validate-md-links should exit 1 on fixtures with broken links');
    }

    public function testDetectsBrokenFileLink(): void
    {
        $this->assertStringContainsString('root.md:7', self::$fixtureOutput);
        $this->assertStringContainsString('broken-link', self::$fixtureOutput);
        $this->assertStringContainsString('subdir/missing.md', self::$fixtureOutput);
    }

    public function testDetectsBrokenAnchorInOtherFile(): void
    {
        $this->assertStringContainsString('root.md:15', self::$fixtureOutput);
        $this->assertStringContainsString('broken-anchor', self::$fixtureOutput);
        $this->assertStringContainsString('#missing-section', self::$fixtureOutput);
    }

    public function testDetectsBrokenLocalAnchor(): void
    {
        $this->assertStringContainsString('root.md:19', self::$fixtureOutput);
        $this->assertStringContainsString('anchor not found: #nonexistent-section', self::$fixtureOutput);
    }

    public function testDetectsBrokenReferenceLink(): void
    {
        $this->assertStringContainsString('root.md:30', self::$fixtureOutput);
        $this->assertStringContainsString('broken-link', self::$fixtureOutput);
        $this->assertStringContainsString('subdir/missing.md', self::$fixtureOutput);
    }

    public function testDetectsBrokenLinkInSibling(): void
    {
        $this->assertStringContainsString('sibling.md:23', self::$fixtureOutput);
        $this->assertStringContainsString('no-such-file.md', self::$fixtureOutput);
    }

    public function testIgnoresFencedCodeBlocks(): void
    {
        // Links inside code blocks should NOT appear in errors
        $this->assertStringNotContainsString('missing-in-code.md', self::$fixtureOutput);
        $this->assertStringNotContainsString('nowhere.md', self::$fixtureOutput);
        $this->assertStringNotContainsString('also-ignored.md', self::$fixtureOutput);
    }

    public function testIgnoresInlineCode(): void
    {
        // Links inside backticks should NOT appear in errors
        $this->assertStringNotContainsString('fake.md', self::$fixtureOutput);
    }

    public function testIgnoresExternalUrls(): void
    {
        $this->assertStringNotContainsString('google.com', self::$fixtureOutput);
        $this->assertStringNotContainsString('mailto:', self::$fixtureOutput);
    }

    public function testValidRussianAnchor(): void
    {
        // Russian anchor link should NOT be reported as error
        $this->assertStringNotContainsString('русский-заголовок', self::$fixtureOutput);
    }

    public function testValidDuplicateHeadingAnchor(): void
    {
        // duplicate-heading-1 should NOT be reported as error
        $this->assertStringNotContainsString('duplicate-heading', self::$fixtureOutput);
    }

    public function testValidLocalAnchor(): void
    {
        // #section-two and #test-document should be valid
        $this->assertStringNotContainsString('#section-two', self::$fixtureOutput);
        $this->assertStringNotContainsString('#test-document', self::$fixtureOutput);
    }

    public function testValidCrossFileAnchor(): void
    {
        // subdir/target.md#section-one should be valid
        $this->assertStringNotContainsString('section-one', self::$fixtureOutput);
    }

    public function testValidReferenceLinks(): void
    {
        // [ref-id] and [second-id] reference links should be valid
        $this->assertStringNotContainsString('ref-id', self::$fixtureOutput);
        $this->assertStringNotContainsString('second-id', self::$fixtureOutput);
    }

    public function testReportsCorrectErrorCount(): void
    {
        $this->assertStringContainsString('Found 5 broken link(s)', self::$fixtureOutput);
    }

    public function testNoFailOptionExitsZero(): void
    {
        $result = self::runScript([self::FIXTURE_DIR, '--no-fail']);

        $this->assertSame(0, $result['exitCode'], '--no-fail should force exit code 0');
    }

    public function testHappyPathOnProjectDocs(): void
    {
        $result = self::runScript([
            'docs/conventions/',
            '--exclude=docs/todo-md/templates/',
            '--exclude=docs/todo-md/AGENTS_TASK_WRITING_GUIDE.md',
        ]);

        $this->assertSame(0, $result['exitCode'],
            "validate-md-links should pass on project docs:\n" . $result['output']);
    }

    public function testConfigFileIsLoadedFromProjectRoot(): void
    {
        // Project root has .md-links.php with excludes — should pass
        $result = self::runScript([]);

        $this->assertSame(0, $result['exitCode'],
            "validate-md-links should load .md-links.php from project root:\n" . $result['output']);
        $this->assertStringContainsString('88 markdown files', $result['output']);
    }

    public function testConfigOptionOverridesDefaultConfigFile(): void
    {
        $tempDir = sys_get_temp_dir() . '/md-links-test-' . uniqid();
        mkdir($tempDir);
        // Create a minimal .md file with a broken link
        file_put_contents($tempDir . '/test.md', '# Test\n\n[broken](missing.md)\n');
        // Create a config that excludes it
        $configFile = $tempDir . '/custom-config.php';
        file_put_contents($configFile, "<?php return ['paths' => [], 'exclude' => []];");

        $result = self::runScript(['--config=' . $configFile]);

        // Cleanup
        unlink($tempDir . '/test.md');
        unlink($configFile);
        rmdir($tempDir);

        // With empty paths, no files scanned — should pass
        $this->assertSame(0, $result['exitCode']);
    }

    /**
     * Run the validate-md-links script and capture output.
     *
     * @param string[] $args
     * @return array{output: string, exitCode: int}
     */
    private static function runScript(array $args): array
    {
        $php = PHP_BINARY;
        $escapedArgs = array_map('escapeshellarg', $args);
        $command = "{$php} " . escapeshellarg(self::SCRIPT) . ' ' . implode(' ', $escapedArgs) . ' 2>&1';

        exec($command, $outputLines, $exitCode);

        return [
            'output' => implode("\n", $outputLines),
            'exitCode' => $exitCode,
        ];
    }
}
