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
        'paths' => ['docs/', 'README.md', 'AGENTS.md'],
        // Максимально допустимая доля слов в английских фразах (по умолчанию 0.02 = 2%).
        'max_ratio' => 0.02,
        // Разрешённые термины (имена собственные и аббревиатуры).
        'allowlist' => [
            'Symfony', 'Doctrine', 'PHPUnit', 'PHPStan', 'Deptrac', 'Composer',
            'Git', 'GitHub', 'Docker', 'MOEX', 'Twig', 'Panther',
            'PHP', 'SQL', 'JSON', 'YAML', 'HTML', 'CSS', 'HTTP', 'HTTPS',
            'DB', 'DBAL', 'ORM', 'API', 'CRUD', 'DDD', 'SOLID', 'CI', 'CD',
            'URL', 'URI', 'ID', 'UUID', 'VO', 'DAO', 'REST', 'RPC', 'SDK',
            'CLI', 'UI', 'UX', 'IO', 'OS', 'PR', 'DTO',
            // Слои и паттерны DDD как термины.
            'Application', 'Domain', 'Infrastructure', 'Integration', 'Presentation',
            'Module', 'Layer', 'Service', 'Event', 'Handler', 'Repository',
            'Entity', 'Component', 'Factory', 'Builder', 'Gateway', 'Calculator',
            'Specification', 'Subscriber', 'Listener', 'Controller', 'Validator',
            'Voter', 'Rule', 'Value', 'Object', 'Enum', 'Migration', 'Fixture',
            'Command', 'Query', 'UseCase', 'Transport',
            // Терминология Markdown и инструментов.
            'Markdown', 'CodeSniffer', 'matter', 'front', 'fenced', 'inline',
            'code', 'block', 'blocks', 'link', 'links', 'reference', 'slug',
            'anchor', 'heading', 'snippet', 'merge', 'release', 'review',
            'warning', 'sniff', 'namespace', 'phpdoc',
            // Английские секции шаблона todo-md (шаблонные заголовки).
            'Concept', 'Goal', 'Story', 'Context', 'Scope', 'Requirements',
            'Must', 'Have', 'Should', 'Plan', 'Verification', 'Risks',
            'Sources', 'Comments', 'Brief', 'Problem', 'Solution', 'Expected',
            'Result', 'Definition', 'Done', 'History', 'MoSCoW', 'SMART',
            // Шаблонные союзы и DDD-термины в тексте конвенций.
            'use', 'case', 'and', 'of', 'web', 'extension', 'task',
            'middleware', 'metadata', 'querybus', 'bus', 'DI',
        ],
        // Стандартные переводы ЖАРГОНА/проектно-специфичных терминов с неоднозначным
        // переводом (НЕ обычных слов с очевидным переводом). validate-language подсказывает
        // перевод рядом со словом, на ratio не влияет; дополняйте под проект.
        'dictionary' => [
            'hook' => 'хук',
            'god object' => 'божественный объект',
        ],
    ],
];
