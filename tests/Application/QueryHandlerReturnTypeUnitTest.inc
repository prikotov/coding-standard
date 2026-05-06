<?php

namespace App\Module\User\Application\UseCase\Query\User\GetReference;

use App\Module\User\Domain\Model\UserModel;

class GetReferenceQueryHandler
{
    public function __invoke(GetReferenceQuery $query): UserModel
    {
    }
}
