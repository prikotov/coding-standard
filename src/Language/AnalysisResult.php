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
     * @param int $anglicismWords Слов в подозрительных фразах (≥2 латинских в mixed-строке).
     * @param int $latinWords Всего латинских слов вне allowlist (info).
     * @param float $ratio anglicismWords / totalWords.
     * @param list<string> $suspiciousPhrases Пробежки из ≥2 латинских слов с хотя бы одним не из allowlist.
     * @param list<string> $sampleWords Образец латинских слов-нарушителей.
     */
    public function __construct(
        public readonly int $totalWords,
        public readonly int $anglicismWords,
        public readonly int $latinWords,
        public readonly float $ratio,
        public readonly array $suspiciousPhrases,
        public readonly array $sampleWords,
    ) {
    }
}
