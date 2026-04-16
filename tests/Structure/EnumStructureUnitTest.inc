<?php

enum BadEnum: string
{
    use SomeTrait;

    public const TYPE = 'bad';

    case BAD_VALUE = 'bad';

    public function label(): string
    {
        return $this->value;
    }
}
