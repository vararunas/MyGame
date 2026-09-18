<?php
declare(strict_types=1);
namespace MyGame\Domain\Country;
final class Country { public function __construct(public readonly string $code, public readonly string $name, public readonly string $currency) {} }
