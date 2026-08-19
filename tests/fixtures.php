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
    // ComponentStructureSniff — Integration/Component is forbidden
    [
        'file' => __DIR__ . '/fixtures/src/Module/Example/Integration/Component/TBusiness/TBusinessPaymentsComponent.inc',
        'errors' => [
            5 => 1,
        ],
        'warnings' => [],
    ],
    // ComponentStructureSniff — Integration/Component interfaces are forbidden too
    [
        'file' => __DIR__ . '/fixtures/src/Module/Example/Integration/Component/TBusiness/TBusinessPaymentsComponentInterface.inc',
        'errors' => [
            5 => 1,
        ],
        'warnings' => [],
    ],
    // ComponentStructureSniff — Infrastructure/Component is valid
    [
        'file' => __DIR__ . '/fixtures/src/Module/Example/Infrastructure/Component/TBusiness/TBusinessPaymentsComponent.inc',
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
    // CommandHandlerEventDispatchSniff — mutating handlers without event dispatch
    [
        'file' => __DIR__ . '/Application/CommandHandlerEventDispatchUnitTest.inc',
        'errors' => [],
        'warnings' => [
            5  => 1, // MissingEventDispatch — save() without dispatch
            14 => 1, // MissingEventDispatch — persist() + flush() without dispatch (historical FailIncomplete case)
            26 => 1, // MissingEventDispatch — remove() + flush() without dispatch
        ],
    ],
    // CommandHandlerEventDispatchSniff — dispatch / read-only / QueryHandler / phpcs:ignore suppression
    [
        'file' => __DIR__ . '/Application/CommandHandlerEventDispatchUnitTestValid.inc',
        'errors' => [],
        'warnings' => [],
    ],
    // CommandHandlerEventDispatchSniff — mutating handler with dispatch in real Command path
    [
        'file' => __DIR__ . '/fixtures/src/Module/Example/Application/UseCase/Command/Publish/PublishCommandHandler.inc',
        'errors' => [],
        'warnings' => [],
    ],
    // CommandHandlerEventDispatchSniff — Query path handler must not trigger the sniff
    [
        'file' => __DIR__ . '/fixtures/src/Module/Example/Application/UseCase/Query/MarkUsed/MarkUsedQueryHandler.inc',
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
    // ValueObjectStructureSniff — test class in ValueObject namespace — valid (not a VO)
    [
        'file' => __DIR__ . '/fixtures/src/Module/Example/Domain/ValueObject/RuntimeContextVoTest.inc',
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
    // DtoStructureSniff — Domain DTO in Domain/Dto/ — wrong path
    [
        'file' => __DIR__ . '/fixtures/src/Module/Example/Domain/Dto/WrongPathDto.inc',
        'errors' => [
            6 => 1,
        ],
        'warnings' => [],
    ],
    // DtoStructureSniff — any class in Domain/Dto/ — wrong path
    [
        'file' => __DIR__ . '/fixtures/src/Module/Example/Domain/Dto/WrongPathPayload.inc',
        'errors' => [
            6 => 1,
        ],
        'warnings' => [],
    ],
    // DtoStructureSniff — Domain DTO in Domain/Service/{GroupName}/ — example of a correct contextual path
    [
        'file' => __DIR__ . '/fixtures/src/Module/Example/Domain/Service/Payment/InitPaymentResultDto.inc',
        'errors' => [],
        'warnings' => [],
    ],
    // DtoStructureSniff — Domain DTO outside Domain/Dto/ — correct contextual path
    [
        'file' => __DIR__ . '/fixtures/src/Module/Example/Domain/Calculator/Pricing/PricingResultDto.inc',
        'errors' => [],
        'warnings' => [],
    ],
    // ServiceStructureSniff — Mapper class in Service/ directory — valid per convention
    [
        'file' => __DIR__ . '/fixtures/src/Module/Example/Infrastructure/Service/PublicAgentSession/SessionPayloadMapper.inc',
        'errors' => [],
        'warnings' => [],
    ],
    // RepositoryStructureSniff — repository without a Domain interface → MustImplementDomainRepositoryInterface
    [
        'file' => __DIR__ . '/fixtures/src/Module/Example/Infrastructure/Repository/Project/NoInterfaceRepository.inc',
        'errors' => [
            10 => 1,
        ],
        'warnings' => [],
    ],
    // RepositoryStructureSniff — calls flush() inside repository → FlushForbidden
    [
        'file' => __DIR__ . '/fixtures/src/Module/Example/Infrastructure/Repository/Project/FlushRepository.inc',
        'errors' => [
            16 => 1,
        ],
        'warnings' => [],
    ],
    // RepositoryStructureSniff — implements Domain interface but no Repository suffix → MissingRepositorySuffix
    [
        'file' => __DIR__ . '/fixtures/src/Module/Example/Infrastructure/Repository/Project/ProjectGateway.inc',
        'errors' => [
            10 => 1,
        ],
        'warnings' => [],
    ],
    // RepositoryStructureSniff — valid repository (extends + implements)
    [
        'file' => __DIR__ . '/fixtures/src/Module/Example/Infrastructure/Repository/Project/ValidProjectRepository.inc',
        'errors' => [],
        'warnings' => [],
    ],
    // RepositoryStructureSniff — In-memory implementation is exempt from ServiceEntityRepository
    [
        'file' => __DIR__ . '/fixtures/src/Module/Example/Infrastructure/Repository/ServiceStatus/InMemoryServiceStatusRepository.inc',
        'errors' => [],
        'warnings' => [],
    ],
    // RepositoryStructureSniff + MethodSignature — write-repository without ServiceEntityRepository is valid
    [
        'file' => __DIR__ . '/fixtures/src/Module/Example/Infrastructure/Repository/Project/ProjectWriteRepository.inc',
        'errors' => [],
        'warnings' => [],
    ],
    // RepositoryStructureSniff + MethodSignature — DBAL Connection dependency + non-conventional method name
    [
        'file' => __DIR__ . '/fixtures/src/Module/Example/Infrastructure/Repository/Project/RawSqlRepository.inc',
        'errors' => [
            11 => 1, // ForbiddenDbalConnectionDependency
            17 => 1, // NonConventionalRepositoryMethod (findSomething)
        ],
        'warnings' => [],
    ],
    // RepositoryStructureSniff — DBAL Connection smuggled via a class property type-hint
    [
        'file' => __DIR__ . '/fixtures/src/Module/Example/Infrastructure/Repository/Project/DbalPropertyRepository.inc',
        'errors' => [
            12 => 1, // ForbiddenDbalConnectionDependency (private readonly Connection $connection)
        ],
        'warnings' => [],
    ],
    // RepositoryStructureSniff — Repository class outside Infrastructure/Repository/
    [
        'file' => __DIR__ . '/fixtures/src/Module/Example/Infrastructure/ServiceOutsideDir/SomeRepository.inc',
        'errors' => [
            8 => 1,
        ],
        'warnings' => [],
    ],
    // RepositoryStructureSniff — implements Domain repository interface outside Infrastructure/Repository/
    [
        'file' => __DIR__ . '/fixtures/src/Module/Example/Integration/Listener/DomainRepoImplOutside.inc',
        'errors' => [
            10 => 1,
        ],
        'warnings' => [],
    ],
    // RepositoryMethodSignatureSniff — public method returns a Doctrine type → DoctrineInfrastructureLeak
    // (also a non-conventional method name → two errors on the same line)
    [
        'file' => __DIR__ . '/fixtures/src/Module/Example/Infrastructure/Repository/Project/LeakReturnRepository.inc',
        'errors' => [
            14 => 2, // NonConventionalRepositoryMethod + DoctrineInfrastructureLeak
        ],
        'warnings' => [],
    ],
    // RepositoryMethodSignatureSniff — public method takes a Doctrine type → DoctrineInfrastructureLeak
    // (also a non-conventional method name → two errors on the same line)
    [
        'file' => __DIR__ . '/fixtures/src/Module/Example/Infrastructure/Repository/Project/LeakParameterRepository.inc',
        'errors' => [
            14 => 2, // NonConventionalRepositoryMethod + DoctrineInfrastructureLeak
        ],
        'warnings' => [],
    ],
    // RepositoryMethodSignatureSniff — bad save/delete/getCount/getByCriteria signatures
    [
        'file' => __DIR__ . '/fixtures/src/Module/Example/Infrastructure/Repository/Project/BadSaveRepository.inc',
        'errors' => [
            13 => 1,
            18 => 1,
            22 => 1,
            27 => 1,
        ],
        'warnings' => [],
    ],
    // RepositoryMethodSignatureSniff — getById must be non-nullable, getOneByCriteria nullable
    [
        'file' => __DIR__ . '/fixtures/src/Module/Example/Infrastructure/Repository/Project/BadReadRepository.inc',
        'errors' => [
            13 => 1,
            18 => 1,
        ],
        'warnings' => [],
    ],
    // RepositoryMethodSignatureSniff — fully valid repository signatures
    [
        'file' => __DIR__ . '/fixtures/src/Module/Example/Infrastructure/Repository/Project/ValidMethodsRepository.inc',
        'errors' => [],
        'warnings' => [],
    ],
    // RepositoryInterfaceContractSniff — valid read interface
    [
        'file' => __DIR__ . '/fixtures/src/Module/Example/Domain/Repository/Payment/ValidPaymentReadRepositoryInterface.inc',
        'errors' => [],
        'warnings' => [],
    ],
    // RepositoryInterfaceContractSniff — valid write interface
    [
        'file' => __DIR__ . '/fixtures/src/Module/Example/Domain/Repository/Payment/ValidPaymentWriteRepositoryInterface.inc',
        'errors' => [],
        'warnings' => [],
    ],
    // RepositoryInterfaceContractSniff — VO repository follows the same contract (returns *Vo); valid
    [
        'file' => __DIR__ . '/fixtures/src/Module/Example/Domain/Repository/Payment/PaymentSummaryRepositoryInterface.inc',
        'errors' => [],
        'warnings' => [],
    ],
    // RepositoryInterfaceContractSniff — valid VO repository: full read contract, element type *Vo
    [
        'file' => __DIR__ . '/fixtures/src/Module/Example/Domain/Repository/Payment/ValidPaymentSummaryRepositoryInterface.inc',
        'errors' => [],
        'warnings' => [],
    ],
    // RepositoryInterfaceContractSniff — getById must be non-nullable → 1 error
    [
        'file' => __DIR__ . '/fixtures/src/Module/Example/Domain/Repository/Payment/BadNullableGetByIdRepositoryInterface.inc',
        'errors' => [
            13 => 1,
        ],
        'warnings' => [],
    ],
    // RepositoryInterfaceContractSniff — exists() must return bool → 1 error
    [
        'file' => __DIR__ . '/fixtures/src/Module/Example/Domain/Repository/Payment/BadExistsRepositoryInterface.inc',
        'errors' => [
            24 => 1,
        ],
        'warnings' => [],
    ],
    // RepositoryInterfaceContractSniff — non-conventional method names → 4 errors
    [
        'file' => __DIR__ . '/fixtures/src/Module/Example/Domain/Repository/Payment/SuspectNamesRepositoryInterface.inc',
        'errors' => [
            13 => 1, // NonConventionalMethodName (findById)
            15 => 1, // NonConventionalMethodName (findByCriteria)
            17 => 1, // NonConventionalMethodName (findOneBy)
            19 => 1, // NonConventionalMethodName (count)
        ],
        'warnings' => [],
    ],
    // RepositoryInterfaceContractSniff — mixing *Model and *Vo in one interface → 1 error
    [
        'file' => __DIR__ . '/fixtures/src/Module/Example/Domain/Repository/Payment/BadVoInEntityRepositoryInterface.inc',
        'errors' => [
            16 => 1, // MixedModelAndValueObject (getOneByCriteria returns *Vo while getById returns *Model)
        ],
        'warnings' => [],
    ],
];
