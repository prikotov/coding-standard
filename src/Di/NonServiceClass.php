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
 *
 * `FormModel` (form data classes) and `Constraint` (custom Symfony validator
 * constraints) are presentation-layer types: they are not part of the Common
 * module mandatory minimum and are only required when such classes actually
 * exist in the module tree.
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
    case FormModel = 'FormModel';
    case Constraint = 'Constraint';

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
            self::FormModel => 'form model',
            self::Constraint => 'validation constraint',
        };
    }

    /** @return self[] mandatory exclude minimum for Common modules */
    public static function moduleWide(): array
    {
        return [self::Dto, self::Event, self::Exception, self::Enum, self::Vo];
    }

    /**
     * @return self[] presentation-layer suffix categories: such classes belong
     * to `apps/*` modules, so only application imports require masks for them;
     * in a Common module their presence is itself a layering violation
     */
    public static function presentationCategories(): array
    {
        return [self::FormModel, self::Constraint];
    }

    /**
     * @return self[] suffix categories proven by existing classes — a mask
     * becomes required for any module kind once such a class appears in it
     */
    public static function contentCategories(): array
    {
        return [self::Dto, self::Event, self::Exception, self::Enum, self::Vo, self::FormModel, self::Constraint];
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

        foreach (self::contentCategories() as $category) {
            if (str_ends_with($fqcn, $category->value)) {
                return $category;
            }
        }

        return null;
    }
}
