<?php

declare(strict_types=1);

namespace Common\Component\ModuleSystem;

interface ModuleInterface
{
    /** @psalm-suppress UnusedMethod */
    public function getModuleDir(): string;

    public function getModuleConfigPath(): string;
}
