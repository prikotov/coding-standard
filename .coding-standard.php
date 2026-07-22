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
        // Разрешённые термины (имена собственные и аббревиатуры).
        'allowlist' => [
            'Symfony', 'Doctrine', 'PHPUnit', 'PHPStan', 'Deptrac', 'Composer',
            'Git', 'GitHub', 'Docker', 'MOEX',
            'PHP', 'SQL', 'JSON', 'YAML', 'HTML', 'CSS', 'HTTP', 'HTTPS',
            'DB', 'DBAL', 'ORM', 'API', 'CRUD', 'DDD', 'SOLID', 'CI', 'CD',
            'URL', 'URI', 'ID', 'UUID', 'VO', 'DAO', 'REST', 'RPC', 'SDK',
            'CLI', 'UI', 'UX', 'IO', 'OS', 'PR', 'DTO',
        ],
    ],
];
