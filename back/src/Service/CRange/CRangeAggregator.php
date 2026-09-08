<?php

namespace App\Service\CRange;

/**
 * Construit les agrégats journaliers (weather_daily_crange) à partir de lignes
 * minutely normalisées (voir CRangeFileReader). Utilisé aussi bien pour un
 * import complet (agrégation en mémoire au fil du fichier) que pour un
 * recalcul ciblé d'un jour déjà partiellement en base (CRangeWriter::recomputeDay).
 *
 * Les lignes doivent être fournies dans l'ordre chronologique croissant (dt) —
 * c'est le cas des deux flux (lecture de fichier, et SELECT ... ORDER BY dt ASC
 * de recomputeDay). L'agrégat pluie (rain_total = cumul de fin de journée moins
 * cumul de début) en dépend.
 */
class CRangeAggregator
{
    /**
     * Ajoute une ligne minutely aux stats du jour concerné (créées si absentes).
     *
     * @param array<int,array> $dayStats indexé par dayEpoch (timestamp UTC de minuit)
     */
    public static function accumulate(array &$dayStats, \DateTimeZone $paris, array $row): void
    {
        $dt = $row['dt'];
        $temp = $row['temp'];
        $dayEpoch = intdiv($dt, 86400) * 86400;

        if (!isset($dayStats[$dayEpoch])) {
            $dayStats[$dayEpoch] = [
                'temp_sum' => 0, 'temp_count' => 0, 'temp_max' => $temp, 'temp_min' => $temp,
                'temp_max_dt' => $dt, 'temp_min_dt' => $dt,
                'temp_0' => $temp, 'temp_12' => null,
                'pressure_sum' => 0, 'pressure_count' => 0, 'pressure_max' => null, 'pressure_min' => null,
                'pressure_max_dt' => null, 'pressure_min_dt' => null,
                'humidity_sum' => 0, 'humidity_count' => 0, 'humidity_max' => null, 'humidity_min' => null,
                'humidity_max_dt' => null, 'humidity_min_dt' => null,
                'wind_speed_sum' => 0, 'wind_speed_count' => 0, 'wind_speed_max' => null, 'wind_speed_max_dt' => null,
                'wind_deg_sum' => 0, 'wind_deg_count' => 0,
                // Pluie : Pluviométrie(mm/h) et Chute de pluie par heure (mm) -> moyenne + maxi + heure du maxi.
                'rain_rate_sum' => 0, 'rain_rate_count' => 0, 'rain_rate_max' => null, 'rain_rate_max_dt' => null,
                'rain_hourly_sum' => 0, 'rain_hourly_count' => 0, 'rain_hourly_max' => null, 'rain_hourly_max_dt' => null,
                // Cumul de pluie (odomètre) : total du jour = dernier - premier relevé,
                // avec repli sur la somme des incréments positifs si l'odomètre a
                // été remis à zéro dans la journée.
                'rain_acc_first' => null, 'rain_acc_first_dt' => null,
                'rain_acc_last' => null, 'rain_acc_last_dt' => null,
                'rain_acc_prev' => null, 'rain_pos_delta_sum' => 0,
            ];
        }
        $stats = &$dayStats[$dayEpoch];

        $stats['temp_sum'] += $temp;
        $stats['temp_count']++;
        // Comparaisons strictes : première occurrence retenue en cas d'égalité,
        // et l'horodatage de l'extrême est capturé au passage.
        if ($temp > $stats['temp_max']) {
            $stats['temp_max'] = $temp;
            $stats['temp_max_dt'] = $dt;
        }
        if ($temp < $stats['temp_min']) {
            $stats['temp_min'] = $temp;
            $stats['temp_min_dt'] = $dt;
        }

        $localHour = (int) (new \DateTime('@' . $dt))->setTimezone($paris)->format('H');
        if ($localHour === 12 && $stats['temp_12'] === null) {
            $stats['temp_12'] = $temp;
        }

        $humidity = $row['humidity'] ?? null;
        if ($humidity !== null) {
            $stats['humidity_sum'] += $humidity;
            $stats['humidity_count']++;
            if ($stats['humidity_max'] === null || $humidity > $stats['humidity_max']) {
                $stats['humidity_max'] = $humidity;
                $stats['humidity_max_dt'] = $dt;
            }
            if ($stats['humidity_min'] === null || $humidity < $stats['humidity_min']) {
                $stats['humidity_min'] = $humidity;
                $stats['humidity_min_dt'] = $dt;
            }
        }

        $pressure = $row['pressure'] ?? null;
        if ($pressure !== null) {
            $stats['pressure_sum'] += $pressure;
            $stats['pressure_count']++;
            if ($stats['pressure_max'] === null || $pressure > $stats['pressure_max']) {
                $stats['pressure_max'] = $pressure;
                $stats['pressure_max_dt'] = $dt;
            }
            if ($stats['pressure_min'] === null || $pressure < $stats['pressure_min']) {
                $stats['pressure_min'] = $pressure;
                $stats['pressure_min_dt'] = $dt;
            }
        }

        $windSpeed = $row['wind_speed'] ?? null;
        if ($windSpeed !== null) {
            $stats['wind_speed_sum'] += $windSpeed;
            $stats['wind_speed_count']++;
            if ($stats['wind_speed_max'] === null || $windSpeed > $stats['wind_speed_max']) {
                $stats['wind_speed_max'] = $windSpeed;
                $stats['wind_speed_max_dt'] = $dt;
            }
        }

        $windDeg = $row['wind_deg'] ?? null;
        if ($windDeg !== null) {
            $stats['wind_deg_sum'] += $windDeg;
            $stats['wind_deg_count']++;
        }

        $rainRate = $row['rain_rate'] ?? null;
        if ($rainRate !== null) {
            $stats['rain_rate_sum'] += $rainRate;
            $stats['rain_rate_count']++;
            if ($stats['rain_rate_max'] === null || $rainRate > $stats['rain_rate_max']) {
                $stats['rain_rate_max'] = $rainRate;
                $stats['rain_rate_max_dt'] = $dt;
            }
        }

        $rainHourly = $row['rain_hourly'] ?? null;
        if ($rainHourly !== null) {
            $stats['rain_hourly_sum'] += $rainHourly;
            $stats['rain_hourly_count']++;
            if ($stats['rain_hourly_max'] === null || $rainHourly > $stats['rain_hourly_max']) {
                $stats['rain_hourly_max'] = $rainHourly;
                $stats['rain_hourly_max_dt'] = $dt;
            }
        }

        $rainAcc = $row['rain_accumulated'] ?? null;
        if ($rainAcc !== null) {
            if ($stats['rain_acc_first'] === null || $dt < $stats['rain_acc_first_dt']) {
                $stats['rain_acc_first'] = $rainAcc;
                $stats['rain_acc_first_dt'] = $dt;
            }
            if ($stats['rain_acc_last'] === null || $dt > $stats['rain_acc_last_dt']) {
                $stats['rain_acc_last'] = $rainAcc;
                $stats['rain_acc_last_dt'] = $dt;
            }
            if ($stats['rain_acc_prev'] !== null) {
                $delta = $rainAcc - $stats['rain_acc_prev'];
                if ($delta > 0) {
                    $stats['rain_pos_delta_sum'] += $delta;
                }
            }
            $stats['rain_acc_prev'] = $rainAcc;
        }
    }

    /** Calcule les moyennes finales à partir des stats accumulées d'un jour. */
    public static function finalize(array $stats): array
    {
        $tempAvg = round($stats['temp_sum'] / $stats['temp_count'], 2);

        $rainTotal = null;
        if ($stats['rain_acc_first'] !== null && $stats['rain_acc_last'] !== null) {
            $delta = $stats['rain_acc_last'] - $stats['rain_acc_first'];
            $rainTotal = $delta >= 0 ? round($delta, 2) : round($stats['rain_pos_delta_sum'], 2);
        }

        return [
            'temp_avg' => $tempAvg,
            'temp_max' => $stats['temp_max'],
            'temp_min' => $stats['temp_min'],
            'temp_0' => $stats['temp_0'],
            'temp_12' => $stats['temp_12'] ?? $tempAvg,
            'temp_max_dt' => $stats['temp_max_dt'] ?? null,
            'temp_min_dt' => $stats['temp_min_dt'] ?? null,
            'pressure_avg' => $stats['pressure_count'] > 0 ? round($stats['pressure_sum'] / $stats['pressure_count'], 2) : 0,
            'pressure_max' => $stats['pressure_max'] ?? 0,
            'pressure_min' => $stats['pressure_min'] ?? 0,
            'pressure_max_dt' => $stats['pressure_max_dt'] ?? null,
            'pressure_min_dt' => $stats['pressure_min_dt'] ?? null,
            'humidity_avg' => $stats['humidity_count'] > 0 ? round($stats['humidity_sum'] / $stats['humidity_count'], 2) : 0,
            'humidity_max' => $stats['humidity_max'] ?? 0,
            'humidity_min' => $stats['humidity_min'] ?? 0,
            'humidity_max_dt' => $stats['humidity_max_dt'] ?? null,
            'humidity_min_dt' => $stats['humidity_min_dt'] ?? null,
            'wind_speed_avg' => $stats['wind_speed_count'] > 0 ? round($stats['wind_speed_sum'] / $stats['wind_speed_count'], 2) : 0,
            'wind_speed_max' => $stats['wind_speed_max'] ?? 0,
            'wind_speed_max_dt' => $stats['wind_speed_max_dt'] ?? null,
            'wind_deg_avg' => $stats['wind_deg_count'] > 0 ? round($stats['wind_deg_sum'] / $stats['wind_deg_count'], 2) : 0,
            // Pluie : null (et non 0) quand la journée n'a aucune donnée de pluie
            // (fichiers antérieurs aux colonnes pluie) -> le front sait alors
            // qu'il n'y a rien à tracer pour ce jour.
            'rain_total' => $rainTotal,
            'rain_rate_avg' => $stats['rain_rate_count'] > 0 ? round($stats['rain_rate_sum'] / $stats['rain_rate_count'], 3) : null,
            'rain_rate_max' => $stats['rain_rate_max'],
            'rain_rate_max_dt' => $stats['rain_rate_max_dt'],
            'rain_hourly_avg' => $stats['rain_hourly_count'] > 0 ? round($stats['rain_hourly_sum'] / $stats['rain_hourly_count'], 3) : null,
            'rain_hourly_max' => $stats['rain_hourly_max'],
            'rain_hourly_max_dt' => $stats['rain_hourly_max_dt'],
        ];
    }
}
