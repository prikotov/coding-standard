<?php

declare(strict_types=1);

namespace Common\Module\Example\Application\UseCase\Query\MarkUsed;

final readonly class MarkUsedQueryHandler
{
    public function __construct(
        private AccessTokenRepositoryInterface $accessTokenRepository,
    ) {
    }

    public function __invoke(MarkUsedQuery $query): array
    {
        return $this->accessTokenRepository->getByCriteria($query->criteria);
    }
}
