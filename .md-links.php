<?php

declare(strict_types=1);

/**
 * Configuration for validate-md-links.
 *
 * Place this file in the project root as .md-links.php.
 * The validator loads it automatically when run without --config.
 *
 * @see docs/conventions/ops/validate-md-links.md
 */

return [
    // Files and directories to scan.
    'paths' => ['docs/', 'README.md', 'AGENTS.md'],

    // Path fragments to exclude from scanning (substring match).
    'exclude' => [
        'docs/todo-md/templates/',
        'docs/todo-md/AGENTS_TASK_WRITING_GUIDE.md',
    ],
];
