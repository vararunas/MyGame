<?php
declare(strict_types=1);
namespace MyGame\Domain\Economy;
final class EconomicParameter { public function __construct(public readonly string $key, public readonly string $label, public readonly float $value, public readonly string $unit, public readonly string $section) {} }
