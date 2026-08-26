<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Di;

/**
 * Non-service class categories that must be excluded from Symfony DI
 * (see docs/conventions/modules/configuration.md).
 *
 * Application `Command`/`Query` are identified by both the suffix and the
 * conventional namespace part `\Application\UseCase\Command|Query\`, so
 * Symfony console commands living in the Presentation layer never match.
 */
enum NonServiceClass: string
{
    case Command = 'Command';
    case Query = 'Query';
    case Dto = 'Dto';
    case Event = 'Event';
    case Exception = 'Exception';
    case Enum = 'Enum';
    case Vo = 'Vo';

    public function fileSuffix(): string
    {
        return $this->value . '.php';
    }

    public function label(): string
    {
        return match ($this) {
            self::Command => 'application command',
            self::Query => 'application query',
            self::Dto => 'DTO',
            self::Event => 'event',
            self::Exception => 'exception',
            self::Enum => 'enum',
            self::Vo => 'value object',
        };
    }

    /** @return self[] categories that require module-wide exclude coverage */
    public static function moduleWide(): array
    {
        return [self::Dto, self::Event, self::Exception, self::Enum, self::Vo];
    }

    /**
     * Classifies a fully qualified class name, or null for service classes
     * (including Symfony console commands outside the Application use case layers).
     */
    public static function classify(string $fqcn): ?self
    {
        $fqcn = ltrim($fqcn, '\\');

        if (str_ends_with($fqcn, 'Command') && str_contains($fqcn, '\\Application\\UseCase\\Command\\')) {
            return self::Command;
        }

        if (str_ends_with($fqcn, 'Query') && str_contains($fqcn, '\\Application\\UseCase\\Query\\')) {
            return self::Query;
        }

        foreach ([self::Dto, self::Event, self::Exception, self::Enum, self::Vo] as $category) {
            if (str_ends_with($fqcn, $category->value)) {
                return $category;
            }
        }

        return null;
    }
}
