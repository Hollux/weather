<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use App\Entity\WeatherHwminutely;

class WeatherHwMinutelyFixtures extends Fixture
{
    public function load(ObjectManager $manager)
    {
        // Exemple de données : tu peux mettre toutes les données que tu veux
        $data = [
            ['2025-11-25', 5],
            ['2025-11-26', 3],
            ['2025-11-27', 7],
        ];

        foreach ($data as [$date, $windSpeed]) {
            $entry = new WeatherHwminutely();
            $entry->setDate(new \DateTime($date));
            $entry->setWindSpeed($windSpeed);
            $entry->setWindSpeedKmh($windSpeed * 3.6); // conversion m/s -> km/h

            $manager->persist($entry);
        }

        $manager->flush();
    }
}
