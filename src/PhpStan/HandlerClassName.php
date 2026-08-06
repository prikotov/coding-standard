<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\PhpStan;

final class HandlerClassName
{
    public static function isUseCaseHandler(string $className): bool
    {
        return self::isHandler(
            $className,
            '\\Application\\UseCase\\Command\\',
            'CommandHandler',
        ) || self::isHandler(
            $className,
            '\\Application\\UseCase\\Query\\',
            'QueryHandler',
        );
    }

    private static function isHandler(string $className, string $namespacePart, string $suffix): bool
    {
        return str_contains($className, $namespacePart)
            && str_ends_with($className, $suffix);
    }
}
