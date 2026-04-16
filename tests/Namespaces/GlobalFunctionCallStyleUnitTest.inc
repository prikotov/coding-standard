<?php

use function count;
use function \trim;
use function Foo\bar;

$items = ['a', 'b'];

$count = \count($items);
$trimmed = \trim(' value ');
\assert($trimmed !== '');
\Foo\bar();

$date = new \DateTimeImmutable();
$plain = count($items);
