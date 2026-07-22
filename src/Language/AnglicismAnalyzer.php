<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Language;

/**
 * Считает долю англицизмов в человекочитаемом тексте.
 *
 * Англицизм — любое латинское слово вне allowlist технических терминов
 * (включая одиночные). Метрика: ratio = anglicismWords / totalWords.
 */
final class AnglicismAnalyzer
{
    /**
     * Базовый allowlist технических терминов (case-insensitive).
     * Имена собственные, аббревиатуры, паттерны из DDD-конвенций.
     */
    public const DEFAULT_ALLOWLIST = [
        // Инструменты и фреймворки.
        'Symfony', 'Doctrine', 'PHPUnit', 'PHPStan', 'Deptrac', 'Composer',
        'Git', 'GitHub', 'Docker',
        // Аббревиатуры.
        'PHP', 'SQL', 'JSON', 'YAML', 'HTML', 'CSS', 'HTTP', 'HTTPS',
        'DB', 'DBAL', 'ORM', 'API', 'CRUD', 'DDD', 'SOLID', 'CI', 'CD',
        'URL', 'URI', 'ID', 'UUID', 'VO', 'DAO', 'REST', 'RPC', 'SDK',
        'CLI', 'UI', 'UX', 'IO', 'OS', 'PR', 'DTO', 'MOEX',
        // Паттерны и термины конвенций.
        'Command', 'Query', 'T-Invest',
        // Слои и паттерны DDD (названия слоёв/концепций как термины).
        'Application', 'Domain', 'Infrastructure', 'Integration', 'Presentation',
        'Module', 'Layer', 'Service', 'Event', 'Handler', 'Repository',
        'Entity', 'Component', 'Factory', 'Builder', 'Gateway', 'Calculator',
        'Specification', 'Subscriber', 'Listener', 'Controller', 'Validator',
        'Voter', 'Rule', 'Value', 'Object', 'Enum', 'Migration', 'Fixture',
        // Терминология Markdown (базовые термины синтаксиса/структуры).
        'Markdown', 'matter', 'front', 'fenced', 'inline', 'code', 'block',
        'blocks', 'link', 'links', 'reference', 'slug', 'anchor', 'heading',
    ];

    /** @var array<string, true> */
    private array $allowlistSet;

    /**
     * @param list<string> $extraAllowlist Дополнительные разрешённые термины.
     */
    public function __construct(array $extraAllowlist = [])
    {
        $this->allowlistSet = [];
        foreach (array_merge(self::DEFAULT_ALLOWLIST, $extraAllowlist) as $term) {
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
        // Только латинские буквы (возможно с дефисами), без кириллицы.
        return preg_match('/^[A-Za-z][A-Za-z-]*[A-Za-z]$|^[A-Za-z]$/', $word) === 1;
    }

    private function isAllowed(string $word): bool
    {
        return isset($this->allowlistSet[mb_strtolower($word)]);
    }
}
