<?php
declare(strict_types=1);

namespace MyGame\Infrastructure\City;

use MyGame\Domain\City\City;

final class CityRepository
{
    /** @return City[] */
    public function all(int $minimumPopulation = 5000): array
    {
        $rows = require __DIR__ . '/../../../database/seeds/cities.php';
        $cities = [];

        foreach ($rows as $row) {
            if ($row['population'] < $minimumPopulation) continue;
            $cities[] = new City($row['name'], $row['population'], $row['x'], $row['y']);
        }

        usort($cities, fn (City $a, City $b) => $b->population <=> $a->population);
        return $cities;
    }
}
