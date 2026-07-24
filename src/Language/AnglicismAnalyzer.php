<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Language;

/**
 * Считает долю англицизмов в человекочитаемом тексте.
 *
 * Англицизм — любое латинское слово вне allowlist технических терминов
 * (включая одиночные). Метрика: ratio = anglicismWords / totalWords.
 * Allowlist задаётся проектом через конфиг, захардкоженного списка нет.
 */
final class AnglicismAnalyzer
{
    /** @var array<string, true> */
    private array $allowlistSet;

    /**
     * @param list<string> $allowlist Разрешённые термины (case-insensitive).
     */
    public function __construct(array $allowlist = [])
    {
        $this->allowlistSet = [];
        foreach ($allowlist as $term) {
            $trimmed = trim($term);
            if ($trimmed !== '') {
                $this->allowlistSet[mb_strtolower($trimmed)] = true;
            }
        }
    }

    public function analyze(string $text): AnalysisResult
    {
        $words = $this->extractWords($text);
        $total = count($words);

        $anglicismCount = 0;
        $sample = [];
        foreach ($words as $word) {
            if (!$this->isLatin($word) || $this->isAllowed($word)) {
                continue;
            }
            $anglicismCount++;
            if (count($sample) < 15) {
                $sample[] = $word;
            }
        }

        $ratio = $total > 0 ? $anglicismCount / $total : 0.0;

        return new AnalysisResult(
            totalWords: $total,
            anglicismWords: $anglicismCount,
            ratio: $ratio,
            sampleWords: array_values(array_unique($sample)),
        );
    }

    /**
     * @return list<string>
     */
    private function extractWords(string $text): array
    {
        // Слова — буквы и внутренние дефисы («read-only», «по-русски», «T-Invest»).
        preg_match_all('/[\p{L}-]+/u', $text, $matches);
        $words = [];
        foreach ($matches[0] as $raw) {
            $word = trim($raw, '-');
            if ($word !== '' && preg_match('/\p{L}/u', $word)) {
                $words[] = $word;
            }
        }

        return $words;
    }

    private function isLatin(string $word): bool
    {
        // Минимум 2 символа: однобуквенные латинские (a, i, x) в русском тексте —
        // артефакты экстрактора, не англицизмы.
        return preg_match('/^[A-Za-z][A-Za-z-]+$/', $word) === 1;
    }

    private function isAllowed(string $word): bool
    {
        return isset($this->allowlistSet[mb_strtolower($word)]);
    }
}
