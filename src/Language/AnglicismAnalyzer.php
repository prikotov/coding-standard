<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Language;

/**
 * Считает долю англицизмов в человекочитаемом тексте и подсказывает
 * стандартные переводы из language.dictionary.
 *
 * Англицизм — любое латинское слово вне allowlist технических терминов
 * (включая одиночные). Метрика: ratio = anglicismWords / totalWords.
 * Allowlist задаётся проектом через конфиг, захардкоженного списка нет.
 *
 * Dictionary (опционально) — карта «английское слово/фраза → перевод»: для
 * англицизмов, встретившихся в тексте и найденных в dictionary, результат
 * несёт подсказку перевода. На ratio не влияет — только диагностика.
 */
final class AnglicismAnalyzer
{
    /** @var array<string, true> */
    private array $allowlistSet;
    /** @var array<string, true> Многословные фразы allowlist (lowercase). */
    private array $allowlistPhrases;

    /** @var array<string, string> Однословные ключи dictionary (lowercase) → перевод. */
    private array $dictionaryWords;

    /** @var array<string, string> Многословные фразы dictionary (lowercase) → перевод. */
    private array $dictionaryPhrases;

    /**
     * @param list<string> $allowlist Разрешённые термины (case-insensitive).
     * @param array<string, string> $dictionary Карта «английское слово/фраза → русский
     *   перевод» (case-insensitive). Для фраз — совпадение по последовательности слов.
     */
    public function __construct(array $allowlist = [], array $dictionary = [])
    {
        $this->allowlistSet = [];
        $this->allowlistPhrases = [];
        foreach ($allowlist as $term) {
            $trimmed = trim($term);
            if ($trimmed === '') {
                continue;
            }
            $key = mb_strtolower($trimmed);
            if (preg_match('/\s/u', $trimmed) === 1) {
                $this->allowlistPhrases[$key] = true;
            } else {
                $this->allowlistSet[$key] = true;
            }
        }

        $this->dictionaryWords = [];
        $this->dictionaryPhrases = [];
        foreach ($dictionary as $english => $russian) {
            $trimmedEnglish = trim((string) $english);
            $trimmedRussian = trim((string) $russian);
            if ($trimmedEnglish === '' || $trimmedRussian === '') {
                continue;
            }
            $key = mb_strtolower($trimmedEnglish);
            if (preg_match('/\s/u', $trimmedEnglish) === 1) {
                $this->dictionaryPhrases[$key] = $trimmedRussian;
            } else {
                $this->dictionaryWords[$key] = $trimmedRussian;
            }
        }
    }

    public function analyze(string $text): AnalysisResult
    {
        if ($this->allowlistPhrases !== []) {
            $text = $this->maskAllowedPhrases($text);
        }
        $words = $this->extractWords($text);
        $total = count($words);

        $anglicismCount = 0;
        $sample = [];
        $foundLower = []; // lowercase-англицизмы, реально встретившиеся в тексте.
        foreach ($words as $word) {
            if (!$this->isLatin($word) || $this->isAllowed($word)) {
                continue;
            }
            $anglicismCount++;
            $foundLower[mb_strtolower($word)] = true;
            if (count($sample) < 15 && !in_array($word, $sample, true)) {
                $sample[] = $word;
            }
        }

        $ratio = $total > 0 ? $anglicismCount / $total : 0.0;

        return new AnalysisResult(
            totalWords: $total,
            anglicismWords: $anglicismCount,
            ratio: $ratio,
            sampleWords: array_values(array_unique($sample)),
            suggestions: $this->buildSuggestions($text, $foundLower),
        );
    }

    /**
     * Подсказки переводов из dictionary для англицизмов, реально встретившихся в тексте.
     *
     * @param array<string, true> $foundLower Lowercase-англицизмы в тексте.
     * @return array<string, string> Англицизм (слово/фраза) → перевод.
     */
    private function buildSuggestions(string $text, array $foundLower): array
    {
        if ($this->dictionaryWords === [] && $this->dictionaryPhrases === []) {
            return [];
        }

        $suggestions = [];
        foreach ($this->dictionaryWords as $english => $russian) {
            if (isset($foundLower[$english])) {
                $suggestions[$english] = $russian;
            }
        }

        if ($this->dictionaryPhrases !== []) {
            $searchText = preg_replace('/\s+/u', ' ', mb_strtolower($text));
            foreach ($this->dictionaryPhrases as $english => $russian) {
                if (!$this->phraseHasAnglicism($english, $foundLower)) {
                    continue;
                }
                if (preg_match($this->phrasePattern($english), (string) $searchText) === 1) {
                    $suggestions[$english] = $russian;
                }
            }
        }

        return $suggestions;
    }

    /**
     * Есть ли в фразе хотя бы одно слово, встретившееся в тексте как англицизм.
     * Не даёт подсказывать фразы, все слова которых — allowlist-термины.
     *
     * @param array<string, true> $foundLower
     */
    private function phraseHasAnglicism(string $phrase, array $foundLower): bool
    {
        foreach (preg_split('/\s+/u', $phrase) ?: [] as $part) {
            if (isset($foundLower[mb_strtolower($part)])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Шаблон совпадения фразы с границами слов (латиница/цифры); пробелы между
     * словами фразы разворачиваются в \s+, чтобы ловить переносы и отступы.
     *
     * @return non-empty-string
     */
    private function phrasePattern(string $phrase): string
    {
        $parts = preg_split('/\s+/u', $phrase) ?: [''];
        $quoted = array_map(static fn (string $p): string => preg_quote($p, '/'), $parts);

        return '/(?<![a-z0-9])' . implode('\s+', $quoted) . '(?![a-z0-9])/u';
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

    /**
     * Удаляет из текста allowlist-фразы (case-insensitive), чтобы их слова
     * не учитывались как англицизмы. Пробелы между словами фразы разворачиваются
     * в \s+ — ловит переносы и отступы.
     */
    private function maskAllowedPhrases(string $text): string
    {
        foreach (array_keys($this->allowlistPhrases) as $phrase) {
            $parts = preg_split('/\s+/u', $phrase) ?: [''];
            $quoted = array_map(static fn (string $p): string => preg_quote($p, '/'), $parts);
            $pattern = '/(?<![a-zA-Z0-9])' . implode('\s+', $quoted) . '(?![a-zA-Z0-9])/ui';
            $text = preg_replace($pattern, ' ', $text) ?? $text;
        }

        return $text;
    }
}
