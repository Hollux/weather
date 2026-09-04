<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\WeatherVille;
use App\Entity\WeatherHWminutely;
use Doctrine\ORM\Mapping\OrderBy;
use App\Entity\WeatherDaily as WeatherDailyEntity;

class WeatherDaily
{
    private HttpClientInterface $client;
    private EntityManagerInterface $em;

    public function __construct(HttpClientInterface $client, EntityManagerInterface $entityManager)
    {
        $this->client = $client;
        $this->em = $entityManager;
    }


    /**
     * Crée et persiste une entité WeatherDaily à partir des statistiques calculées.
     *
     * @param int $dayEpoch minuit UTC du jour (FLOOR(dt/86400)*86400) — même convention
     *                      que WeatherHWminutelyRepository::findDaysNotInDaily()
     */
    public function createWeatherDaily(int $dayEpoch, array $stats, EntityManagerInterface $em)
    {
        $daily = new WeatherDailyEntity();
        $daily->setDay($dayEpoch);

        $daily->setTempAvg($stats['temp_avg']);
        $daily->setTempMax($stats['temp_max']);
        $daily->setTempMin($stats['temp_min']);
        $daily->setTemp0($stats['temp_0']);
        $daily->setTemp12($stats['temp_12']);

        $daily->setPressureAvg($stats['pressure_avg']);
        $daily->setPressureMax($stats['pressure_max']);
        $daily->setPressureMin($stats['pressure_min']);

        $daily->setHumidityAvg($stats['humidity_avg']);
        $daily->setHumidityMax($stats['humidity_max']);
        $daily->setHumidityMin($stats['humidity_min']);

        $daily->setUviAvg($stats['uvi_avg']);
        $daily->setUviMax($stats['uvi_max']);

        $daily->setWindSpeedAvg($stats['wind_speed_avg']);
        $daily->setWindSpeedMax($stats['wind_speed_max']);
        $daily->setWindDegAvg($stats['wind_deg_avg']);

        $em->persist($daily);
    }


    /**
     * Calcule les statistiques quotidiennes à partir des enregistrements minutely
     */
    public function computeDayStats(array $rows): array
    {
        $temps = array_map('floatval', array_column($rows, 'temp'));
        $pressures = array_map('floatval', array_column($rows, 'pressure'));
        $humidities = array_map('floatval', array_column($rows, 'humidity'));
        $uvis = array_map('floatval', array_column($rows, 'uvi'));
        $windSpeeds = array_map('floatval', array_column($rows, 'wind_speed_kmh'));
        $windDegs = array_map('floatval', array_column($rows, 'wind_deg'));

        return [
            // Températures
            'temp_avg' => round(array_sum($temps) / count($temps), 2),
            'temp_max' => round(max($temps), 2),
            'temp_min' => round(min($temps), 2),
            'temp_0'   => round((float) $temps[0], 2),
            'temp_12'  => round((float) $temps[(int)(count($temps) / 2)], 2),

            // Pression
            'pressure_avg' => round(array_sum($pressures) / count($pressures), 2),
            'pressure_max' => round(max($pressures), 2),
            'pressure_min' => round(min($pressures), 2),

            // Humidité
            'humidity_avg' => round(array_sum($humidities) / count($humidities), 2),
            'humidity_max' => round(max($humidities), 2),
            'humidity_min' => round(min($humidities), 2),

            // UV
            'uvi_avg' => round(array_sum($uvis) / count($uvis), 2),
            'uvi_max' => round(max($uvis), 2),
            // Vent
            'wind_speed_avg' => round(array_sum($windSpeeds) / count($windSpeeds), 2),
            'wind_speed_max' => round(max($windSpeeds), 2),
            'wind_deg_avg' => round(array_sum($windDegs) / count($windDegs), 2),
        ];
    }


    /**
     * Génère les lignes weather_daily manquantes à partir de weather_hwminutely.
     * Job interne : lancé par la commande app:weather:aggregate (cron), et aussi
     * exposé en secours via la route HTTP /generateWeatherDaily.
     *
     * @return int nombre de jours générés
     */
    public function generateMissingDaily(): int
    {
        /** @var \App\Repository\WeatherHWminutelyRepository $repoMinutely */
        $repoMinutely = $this->em->getRepository(WeatherHWminutely::class);

        // On n'agrège que des jours terminés : le jour en cours (minuit UTC courant)
        // est encore incomplet.
        $todayEpoch = intdiv(time(), 86400) * 86400;

        // Jours présents dans hwminutely mais pas encore dans weather_daily.
        $days = $repoMinutely->findDaysNotInDaily();
        $count = 0;

        foreach ($days as $dayEpoch) {
            if ($dayEpoch >= $todayEpoch) {
                continue;
            }
            $rows = $repoMinutely->findDayData($dayEpoch);
            // Un jour doit avoir au moins 30 enregistrements pour être pris en compte.
            if (count($rows) < 30) {
                continue;
            }
            $stats = $this->computeDayStats($rows);
            $this->createWeatherDaily($dayEpoch, $stats, $this->em);
            $count++;

            // Pour éviter d'exploser la RAM.
            if ($count % 10 === 0) {
                $this->em->flush();
                $this->em->clear();
            }
        }

        $this->em->flush();
        $this->em->clear();

        return $count;
    }


    /**
     * Récupère les enregistrements WeatherDaily entre deux dates
     */
    public function getDailyWithMinMax($min, $max)
    {
        $datas = $this->em->getRepository(WeatherDailyEntity::class)
            ->createQueryBuilder('w')
            ->select('w')
            ->setParameter('min', $min)
            ->setParameter('max', $max)
            ->Where('w.day >= :min')
            ->AndWhere('w.day <= :max')
            ->orderBy('w.day', 'ASC')
            ->getQuery()
            ->getResult();

        return $datas;
    }
}
