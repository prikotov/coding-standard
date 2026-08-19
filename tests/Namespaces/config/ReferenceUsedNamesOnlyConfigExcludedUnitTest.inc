<?php

// Fixture for ReferenceUsedNamesOnlySniff exclude-pattern: files under */config/* are not
// checked because Symfony configuration conventionally references classes by FQCN keys.

return [
    \Task\Common\Module\Example\Infrastructure\ExampleModule::class => ['all' => true],
];
