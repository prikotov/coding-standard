<?php

namespace App\Module\User\Application\UseCase\Command\User\Create;

use App\Module\User\Domain\Model\UserModel;

class CreateCommandHandler
{
    public function __invoke(CreateCommand $command): UserModel
    {
    }
}
