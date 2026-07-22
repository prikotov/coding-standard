<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Language;

/**
 * Результат анализа текста на англицизмы.
 */
final class AnalysisResult
{
    /**
     * @param int $totalWords Всего слов-токенов в тексте.
     * @param int $anglicismWords Латинских слов вне allowlist (включая одиночные).
     * @param float $ratio anglicismWords / totalWords.
     * @param list<string> $suspiciousPhrases Английские обороты (≥2 слова) в mixed-строках — для примеров в отчёте.
     * @param list<string> $sampleWords Образец латинских слов-нарушителей.
     */
    public function __construct(
        public readonly int $totalWords,
        public readonly int $anglicismWords,
        public readonly float $ratio,
        public readonly array $suspiciousPhrases,
        public readonly array $sampleWords,
    ) {
    }
}
