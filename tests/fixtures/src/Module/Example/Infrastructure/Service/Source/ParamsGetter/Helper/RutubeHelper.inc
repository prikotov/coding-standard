<?php

// Helper class in Service/ directory — should pass per convention
namespace App\Module\Example\Infrastructure\Service\Source\ParamsGetter\Helper;

final class RutubeHelper
{
    public static function getVideoId(string $uri): string
    {
        return $uri;
    }
}
