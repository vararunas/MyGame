<?php
declare(strict_types=1);

namespace MyGame\Domain\City;

final class City
{
    public function __construct(
        public readonly string $name,
        public readonly int $population,
        public readonly float $x,
        public readonly float $y,
    ) {}
}
