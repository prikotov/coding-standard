<?php

declare(strict_types=1);

return [
    // PresentationLayerNamespaceSniff — Application inside Web (forbidden)
    [
        'file' => __DIR__ . '/Namespaces/PresentationLayerNamespaceUnitTest.inc',
        'errors' => [
            3 => 1,
        ],
        'warnings' => [],
    ],
    // PresentationLayerNamespaceSniff — Application inside Api (forbidden)
    [
        'file' => __DIR__ . '/Namespaces/PresentationLayerNamespaceUnitTestApp.inc',
        'errors' => [
            3 => 1,
        ],
        'warnings' => [],
    ],
    // PresentationLayerNamespaceSniff — Domain inside Api (forbidden)
    [
        'file' => __DIR__ . '/Namespaces/PresentationLayerNamespaceUnitTestDomain.inc',
        'errors' => [
            3 => 1,
        ],
        'warnings' => [],
    ],
    // PresentationLayerNamespaceSniff — Application in Common (valid)
    [
        'file' => __DIR__ . '/Namespaces/PresentationLayerNamespaceUnitTestValidCommon.inc',
        'errors' => [],
        'warnings' => [],
    ],
    // PresentationLayerNamespaceSniff — Controller in Web (valid, no inner layer)
    [
        'file' => __DIR__ . '/Namespaces/PresentationLayerNamespaceUnitTestValidWeb.inc',
        'errors' => [],
        'warnings' => [],
    ],
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
    // CommandHandlerReturnTypeSniff — returns forbidden type (Model)
    [
        'file' => __DIR__ . '/Application/CommandHandlerReturnTypeUnitTest.inc',
        'errors' => [
            9 => 1, // ForbiddenReturnType — UserModel
        ],
        'warnings' => [],
    ],
    // CommandHandlerReturnTypeSniff — returns void (valid)
    [
        'file' => __DIR__ . '/Application/CommandHandlerReturnTypeUnitTestValid.inc',
        'errors' => [],
        'warnings' => [],
    ],
    // QueryHandlerReturnTypeSniff — returns forbidden type (Model)
    [
        'file' => __DIR__ . '/Application/QueryHandlerReturnTypeUnitTest.inc',
        'errors' => [
            9 => 1, // ForbiddenReturnType — UserModel
        ],
        'warnings' => [],
    ],
    // QueryHandlerReturnTypeSniff — returns valid DTO
    [
        'file' => __DIR__ . '/Application/QueryHandlerReturnTypeUnitTestValid.inc',
        'errors' => [],
        'warnings' => [],
    ],
    // ValueObjectStructureSniff — bad cases
    [
        'file' => __DIR__ . '/Structure/ValueObjectStructureUnitTest.inc',
        'errors' => [
            5  => 1, // FinalReadonlyRequired
            7  => 1, // ForbiddenMembers — trait
            11 => 1, // NonReadonlyProperty
            13 => 2, // NonReadonlyProperty + ForbiddenPropertyType
            20 => 1, // ForbiddenMagicMethod
            24 => 1, // VoidReturnForbidden
            33 => 1, // ToReturnSelfForbidden
            38 => 1, // ForbiddenStaticMethod
        ],
        'warnings' => [
            3 => 1, // NamespaceMismatch
        ],
    ],
    // ValueObjectStructureSniff — valid cases
    [
        'file' => __DIR__ . '/Structure/ValueObjectStructureUnitTestValid.inc',
        'errors' => [],
        'warnings' => [],
    ],
    // ServiceStructureSniff — no companion Service class in directory
    [
        'file' => __DIR__ . '/fixtures/src/Module/Example/Service/NoCompanion/SomeCatalog.inc',
        'errors' => [
            6 => 1, // NoServiceSuffix
        ],
        'warnings' => [],
    ],
    // ServiceStructureSniff — Service class without interface
    [
        'file' => __DIR__ . '/fixtures/src/Module/Example/Service/WithServiceNoInterface/SomeService.inc',
        'errors' => [
            6 => 1, // NoInterface
        ],
        'warnings' => [],
    ],
    // ServiceStructureSniff — companion class with Service nearby (no error on companion)
    [
        'file' => __DIR__ . '/fixtures/src/Module/Example/Service/WithServiceNoInterface/SomeHelper.inc',
        'errors' => [],
        'warnings' => [],
    ],
    // ServiceStructureSniff — valid Service with implements
    [
        'file' => __DIR__ . '/fixtures/src/Module/Example/Service/WithValidService/SomeService.inc',
        'errors' => [],
        'warnings' => [],
    ],
    // ServiceStructureSniff — companion class with valid Service nearby
    [
        'file' => __DIR__ . '/fixtures/src/Module/Example/Service/WithValidService/SomeHelper.inc',
        'errors' => [],
        'warnings' => [],
    ],
    // ServiceStructureSniff — Service class outside Service/ directory
    [
        'file' => __DIR__ . '/fixtures/src/Module/Example/Infrastructure/ServiceOutsideDir/SomeService.inc',
        'errors' => [
            6 => 1, // ServiceOutsideServiceDirectory
        ],
        'warnings' => [],
    ],
    // ServiceStructureSniff — class implements Domain\Service\ interface but outside Service/ directory
    [
        'file' => __DIR__ . '/fixtures/src/Module/Example/Integration/Broadcaster/NullNotificationBroadcaster.inc',
        'errors' => [
            8 => 1, // DomainServiceImplOutsideServiceDirectory
        ],
        'warnings' => [],
    ],
    // ServiceStructureSniff — class implements Domain\Service\ interface via FQCN but outside Service/ directory
    [
        'file' => __DIR__ . '/fixtures/src/Module/Example/Integration/Broadcaster/FqcnBroadcaster.inc',
        'errors' => [
            6 => 1, // DomainServiceImplOutsideServiceDirectory
        ],
        'warnings' => [],
    ],
    // ServiceStructureSniff — class implements non-Service interface outside Service/ — valid
    [
        'file' => __DIR__ . '/fixtures/src/Module/Example/Integration/Listener/LogOnSomethingListener.inc',
        'errors' => [],
        'warnings' => [],
    ],
    // ValueObjectStructureSniff — class in ValueObject namespace without Vo suffix
    [
        'file' => __DIR__ . '/fixtures/src/Module/Example/Domain/ValueObject/RuntimeContext.inc',
        'errors' => [
            6 => 1, // MissingVoSuffix
        ],
        'warnings' => [],
    ],
    // ValueObjectStructureSniff — class in ValueObject namespace with Vo suffix — valid
    [
        'file' => __DIR__ . '/fixtures/src/Module/Example/Domain/ValueObject/EmailVo.inc',
        'errors' => [],
        'warnings' => [],
    ],
    // ServiceStructureSniff — Helper class in Service/ directory — valid per convention
    [
        'file' => __DIR__ . '/fixtures/src/Module/Example/Infrastructure/Service/Source/ParamsGetter/Helper/RutubeHelper.inc',
        'errors' => [],
        'warnings' => [],
    ],
    // ServiceStructureSniff — Factory class in Service/ directory — valid per convention
    [
        'file' => __DIR__ . '/fixtures/src/Module/Example/Application/Service/SomeService/PaymentDtoFactory.inc',
        'errors' => [],
        'warnings' => [],
    ],
];
