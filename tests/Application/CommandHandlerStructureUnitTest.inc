<?php

final class SomeCommandHandler
{
    public string $state;

    public function __construct(public string $id)
    {
    }

    public function handle(): void
    {
    }
}
