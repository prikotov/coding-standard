<?php

trait SampleTrait
{
}

final class CreateSomethingCommand
{
    public string $payload;

    public function __construct(public string $id, public string $type)
    {
        if ($type === '') {
            throw new InvalidArgumentException('type required');
        }

        $this->payload = $id;
    }

    public function meta(): array
    {
        return [];
    }

    use SampleTrait;

    public const ACTION = 'create';
}
