<?php

use function Foo\bar;

$items = ['a', 'b'];

$count = count($items);
$trimmed = trim(' value ');
assert($trimmed !== '');
\Foo\bar();

$date = new \DateTimeImmutable();
$plain = array_map(static fn (string $item): string => trim($item), $items);
