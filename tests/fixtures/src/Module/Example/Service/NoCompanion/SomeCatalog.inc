<?php

// No Service class in this directory — should error on line 5
namespace App\Module\Example\Service\NoCompanion;

final readonly class SomeCatalog
{
    public function all(): array
    {
        return [];
    }
}
