<?php

declare(strict_types=1);

// Project-level configuration for prikotov/coding-standard.
// Required by the package's sniffs (ConfigRequiredSniff). The `docs_path`
// setting points error messages at the conventions documentation; the sniffs
// stay silent without this file.
return [
    'docs_path' => 'docs/conventions',

    // Конфигурация метрик качества production-кода.
    // Корни исходников определяются из Composer autoload; пути ниже исключаются
    // даже при их попадании в область автозагрузки.
    'metrics' => [
        // Корень артефактов. Структура каталогов повторяет пути исходников.
        'report_dir' => 'var/metrics',
        'report_layout' => 'mirror',
        'exclude' => [
            'vendor/', '.git/', 'var/', 'tmp/',
            'migrations/', 'config/', 'docs/',
            'public/', 'templates/', 'translations/',
        ],
        'module_patterns' => [
            'src/Module/*',
            'apps/*/src/Module/*',
        ],
        // Допустимые границы project-level правил. Сборщик преобразует
        // превышения в findings; значения пересматриваются по распределениям.
        'thresholds' => [
            'method' => [
                'loc' => 50,
                'cc' => 10,
            ],
            'class' => [
                'loc' => 300,
                'wmc' => 50,
                'max_cc' => 10,
                'lcom4_components' => 1,
                'ce' => 10,
            ],
            'module' => [
                'external_dependency_share' => 0.5,
                'cycles' => 0,
            ],
        ],
    ],

    // Конфигурация validate-language (поиск англицизмов в русскоязычной документации).
    'language' => [
        'paths' => ['docs/', 'README.md', 'AGENTS.md'],
        // Максимально допустимая доля слов в английских фразах (по умолчанию 0.02 = 2%).
        'max_ratio' => 0.02,
        // Разрешённые термины (имена собственные и аббревиатуры).
        'allowlist' => [
            // 1. Внешние утилиты (инструменты разработки и анализа).
            'PHPUnit', 'PHPStan', 'PHPMD', 'Deptrac', 'Composer', 'CodeSniffer',
            // 2. Технологии, сервисы, библиотеки.
            'Symfony', 'Doctrine', 'Twig', 'Panther',
            'Git', 'GitHub', 'Docker',
            'CI', 'CD',
            // 3. Языки, стандарты, форматы.
            'PHP', 'SQL', 'JSON', 'YAML', 'XML', 'PSR', 'HTML', 'CSS', 'HTTP', 'HTTPS',
            'Markdown', 'PHPDoc',
            // 4. Общеупотребимые термины и аббревиатуры.
            'URL', 'URI', 'ID', 'UUID', 'VO', 'DAO', 'REST', 'RPC', 'SDK',
            'CLI', 'UI', 'UX', 'IO', 'OS', 'PR', 'DI',
            'DB', 'DBAL', 'ORM', 'API', 'CRUD', 'DTO',
            'DDD', 'SOLID',
            'README', 'CHANGELOG', 'in-memory', 'web', 'task', 'metadata',
            'slug', 'namespace',
            'merge', 'release', 'warning', 'front matter', 'Code Conventions', 'Code Style',
            // 5. Термины конвенций — слои, паттерны, классовые типы.
            'Application', 'Domain', 'Infrastructure', 'Integration', 'Presentation',
            'Module', 'Layer', 'Service', 'Event', 'Handler', 'Repository',
            'Entity', 'Component', 'Factory', 'Builder', 'Gateway', 'Calculator',
            'Specification', 'Subscriber', 'Listener', 'Controller', 'Validator', 'Extension', 'Middleware',
            'Voter', 'Rule', 'Grant', 'Route', 'Value', 'Object', 'Enum', 'Migration', 'Fixture',
            'Command', 'Query', 'UseCase', 'use case', 'Transport', 'Mapper', 'Helper', 'CriteriaMapper',
            'Permission', 'Action', 'Request', 'Response',
            'FormModel', 'FormType', 'CommandBus', 'QueryBus',
            'Unit of Work',
            // 6. Соглашения именования.
            'PascalCase', 'camelCase', 'kebab-case',
            // 7. Английские заголовки и термины шаблона todo-md.
            'Concept and Goal', 'Context and Scope', 'Implementation Plan',
            'Definition of Done', 'Definition of Ready', 'Risks and Dependencies',
            'Change History', 'Solution Design', 'Release Notes and Deployment',
            'Must Have', 'Should Have', 'Could Have', "Won't Have",
            'User Story', 'Job Story',
            'Requirements', 'Verification', 'Sources', 'Comments',
            'Goal', 'Story', 'MoSCoW', 'SMART', 'INVEST',
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
