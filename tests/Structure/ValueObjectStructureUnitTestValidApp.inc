<?php

namespace Project\Common\Module\User\Application\ValueObject;

use Project\Common\Module\User\Application\Dto\ProfileDto;

final readonly class UserProfileVo
{
    public function __construct(
        private ProfileDto $profile,
        private string $locale,
    ) {}

    public function getLocale(): string
    {
        return $this->locale;
    }
}
