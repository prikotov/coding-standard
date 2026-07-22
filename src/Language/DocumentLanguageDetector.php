<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Language;

/**
 * Определяет язык документа по имени файла и front matter.
 *
 * Приоритет: front matter `lang` > суффикс имени файла `.<lang>.md`.
 * По умолчанию (нет признака) — русский (`ru`): документы без явной пометки
 * считаются русскоязычными и проверяются на англицизмы.
 */
final class DocumentLanguageDetector
{
    public const DEFAULT_LANGUAGE = 'ru';

    /**
     * @param string $filePath Путь к файлу (для имени).
     * @param string $content  Содержимое (для front matter).
     */
    public function detect(string $filePath, string $content): string
    {
        $fromFrontMatter = $this->detectFromFrontMatter($content);
        if ($fromFrontMatter !== null) {
            return $fromFrontMatter;
        }

        $fromFilename = $this->detectFromFilename($filePath);
        if ($fromFilename !== null) {
            return $fromFilename;
        }

        return self::DEFAULT_LANGUAGE;
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
