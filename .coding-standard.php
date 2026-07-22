<?php

declare(strict_types=1);

// Project-level configuration for prikotov/coding-standard.
// Required by the package's sniffs (ConfigRequiredSniff). The `docs_path`
// setting points error messages at the conventions documentation; the sniffs
// stay silent without this file.
return [
    'docs_path' => 'docs/conventions',

    // Конфигурация validate-language (поиск англицизмов в русскоязычной документации).
    'language' => [
        'paths' => ['docs/', 'todo/', 'README.md', 'AGENTS.md'],
        // Максимально допустимая доля слов в английских фразах (по умолчанию 0.02 = 2%).
        'max_ratio' => 0.02,
    ],
];
