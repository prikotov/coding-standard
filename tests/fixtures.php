<?php

declare(strict_types=1);

return [
    [
        'file' => __DIR__ . '/Application/CommandQueryStructureUnitTest.inc',
        'errors' => [
            9  => 1,
            13 => 1,
            20 => 1,
            25 => 1,
            27 => 1,
        ],
        'warnings' => [],
    ],
    [
        'file' => __DIR__ . '/Application/CommandQueryStructureUnitTestMissingConstructor.inc',
        'errors' => [
            3 => 1,
        ],
        'warnings' => [],
    ],
    [
        'file' => __DIR__ . '/Application/CommandQueryStructureUnitTestValid.inc',
        'errors' => [],
        'warnings' => [],
    ],
    [
        'file' => __DIR__ . '/Application/CommandHandlerStructureUnitTest.inc',
        'errors' => [
            3 => 1,
            5 => 1,
            7 => 1,
            11 => 1,
        ],
        'warnings' => [],
    ],
    [
        'file' => __DIR__ . '/Application/CommandHandlerStructureUnitTestValid.inc',
        'errors' => [],
        'warnings' => [],
    ],
    [
        'file' => __DIR__ . '/Structure/DtoStructureUnitTest.inc',
        'errors' => [
            3  => 1,
            5  => 1,
            7  => 1,
            9  => 1,
            13 => 1,
            16 => 1,
        ],
        'warnings' => [],
    ],
    [
        'file' => __DIR__ . '/Structure/DtoStructureUnitTestValid.inc',
        'errors' => [],
        'warnings' => [],
    ],
    [
        'file' => __DIR__ . '/Structure/EnumStructureUnitTest.inc',
        'errors' => [
            5  => 1,
            7  => 1,
            9  => 1,
            11 => 1,
        ],
        'warnings' => [],
    ],
    [
        'file' => __DIR__ . '/Structure/EnumStructureUnitTestValid.inc',
        'errors' => [],
        'warnings' => [],
    ],
    [
        'file' => __DIR__ . '/Namespaces/GlobalFunctionCallStyleUnitTest.inc',
        'errors' => [
            3  => 1,
            4  => 1,
            9  => 1,
            10 => 1,
            11 => 1,
        ],
        'warnings' => [],
    ],
    [
        'file' => __DIR__ . '/Namespaces/GlobalFunctionCallStyleUnitTestValid.inc',
        'errors' => [],
        'warnings' => [],
    ],
    [
        'file' => __DIR__ . '/fixtures/src/Module/Example/Application/UseCase/Command/Foo/Bar/BarCommand.inc',
        'errors' => [],
        'warnings' => [],
    ],
    [
        'file' => __DIR__ . '/fixtures/src/Module/Example/Application/UseCase/Command/Foo/Right/LeftCommand.inc',
        'errors' => [
            7 => 1,
        ],
        'warnings' => [],
    ],
    [
        'file' => __DIR__ . '/fixtures/src/Module/Example/Application/UseCase/Command/Foo/Baz/BazCommandWrong.inc',
        'errors' => [
            7 => 1,
        ],
        'warnings' => [],
    ],
    [
        'file' => __DIR__ . '/fixtures/src/Module/Example/Application/UseCase/Command/Foo/Baz/BazQuery.inc',
        'errors' => [
            7 => 1,
        ],
        'warnings' => [],
    ],
    [
        'file' => __DIR__ . '/fixtures/src/Module/Example/Application/UseCase/Query/Foo/Find/FindQueryHandler.inc',
        'errors' => [
            5 => 1,
        ],
        'warnings' => [],
    ],
    // Root namespace prefix (Task\) — no NamespaceMismatch error
    [
        'file' => __DIR__ . '/fixtures/src/Module/Example/Application/UseCase/Command/Foo/BazTask/BazTaskCommand.inc',
        'errors' => [],
        'warnings' => [],
    ],
];
