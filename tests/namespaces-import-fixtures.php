<?php

declare(strict_types=1);

return [
    // ReferenceUsedNamesOnly — FQCN with a leading backslash in the body instead of a use import
    [
        'file' => __DIR__ . '/Namespaces/ReferenceUsedNamesOnlyUnitTest.inc',
        'errors' => [
            12 => 1, // ReferenceViaFullyQualifiedName — \Task\...\PaymentResultDto instead of use
        ],
        'warnings' => [],
    ],
    // ReferenceUsedNamesOnly — use import, FQCN global class and FQCN exception are allowed
    [
        'file' => __DIR__ . '/Namespaces/ReferenceUsedNamesOnlyUnitTestValid.inc',
        'errors' => [],
        'warnings' => [],
    ],
    // DisallowGroupUse — use Foo\{Bar, Baz};
    [
        'file' => __DIR__ . '/Namespaces/DisallowGroupUseUnitTest.inc',
        'errors' => [
            7 => 1, // DisallowedGroupUse
        ],
        'warnings' => [],
    ],
    // ReferenceUsedNamesOnly — files under */config/* are excluded: Symfony configs use FQCN keys
    [
        'file' => __DIR__ . '/Namespaces/config/ReferenceUsedNamesOnlyConfigExcludedUnitTest.inc',
        'errors' => [],
        'warnings' => [],
    ],
];
