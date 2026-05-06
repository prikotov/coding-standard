<?php

namespace App\Module\User\Application\UseCase\Query\User\GetById;

use App\Module\User\Application\UseCase\Query\User\GetById\UserDto;

class GetByIdQueryHandler
{
    public function __invoke(GetByIdQuery $query): UserDto
    {
    }
}
