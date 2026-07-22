<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Language;

/**
 * Результат определения языка документа.
 */
final class LanguageDetection
{
    public function __construct(
        public readonly string $language,
        public readonly bool $conflict,
        public readonly ?string $fromFrontMatter,
        public readonly ?string $fromFilename,
    ) {
    }
}
