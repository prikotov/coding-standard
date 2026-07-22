<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Language;

/**
 * Определяет язык документа по имени файла и front matter.
 *
 * Признаки языка:
 *  - front matter: `lang: en`;
 *  - суффикс имени файла: `name.en.md`.
 *
 * Если язык указан в обоих местах и значения совпадают — берётся оно.
 * Если различаются — это ошибка конфигурации: {@see LanguageDetection::$conflict}.
 * Без признака язык считается русским (DocumentLanguageDetector::DEFAULT_LANGUAGE).
 */
final class DocumentLanguageDetector
{
    public const DEFAULT_LANGUAGE = 'ru';

    public function detect(string $filePath, string $content): LanguageDetection
    {
        $fromFrontMatter = $this->detectFromFrontMatter($content);
        $fromFilename = $this->detectFromFilename($filePath);

        if ($fromFrontMatter !== null && $fromFilename !== null && $fromFrontMatter !== $fromFilename) {
            return new LanguageDetection(
                language: self::DEFAULT_LANGUAGE,
                conflict: true,
                fromFrontMatter: $fromFrontMatter,
                fromFilename: $fromFilename,
            );
        }

        $language = $fromFrontMatter ?? $fromFilename ?? self::DEFAULT_LANGUAGE;

        return new LanguageDetection(
            language: $language,
            conflict: false,
            fromFrontMatter: $fromFrontMatter,
            fromFilename: $fromFilename,
        );
    }

    private function detectFromFrontMatter(string $content): ?string
    {
        if (!str_starts_with($content, "---\n")) {
            return null;
        }
        $end = strpos($content, "\n---\n", 4);
        if ($end === false) {
            return null;
        }
        $yaml = substr($content, 4, $end - 4);

        if (preg_match('/^lang:\s*["\']?([A-Za-z]{2}(?:-[A-Za-z]{2,4})?)["\']?\s*$/m', $yaml, $m)) {
            return mb_strtolower($m[1]);
        }

        return null;
    }

    private function detectFromFilename(string $filePath): ?string
    {
        $basename = basename($filePath);
        // name.<lang>.md — код языка из 2 букв (возможно с регионом -RU) перед .md.
        if (preg_match('/\.([a-z]{2}(?:-[a-z]{2,4})?)\.md$/i', $basename, $m)) {
            return mb_strtolower($m[1]);
        }

        return null;
    }
}
