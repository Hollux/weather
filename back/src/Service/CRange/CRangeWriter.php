<?php

namespace App\Service\CRange;

use Doctrine\DBAL\Connection;

/** Écrit les lignes minutely et les agrégats journaliers CRange en base. */
class CRangeWriter
{
    public function __construct(private Connection $connection)
    {
    }

    /**
     * @param array<int,array{dt:int,temp:float,humidity:?float,pressure:?float,wind_speed:?float,wind_deg:?float,rain_rate?:?float,rain_hourly?:?float,rain_accumulated?:?float}> $rows
     *
     * @return int nombre de lignes réellement insérées
     *
     * Nouvelle ligne (dt absent) : insertion complète. Ligne déjà présente :
     * seules les colonnes pluie (rain_rate / rain_hourly / rain_accumulated)
     * sont mises à jour — temp / pression / vent ne sont jamais réécrits. C'est
     * ce qui permet de backfiller la pluie sur les lignes minutely existantes
     * (import de "orégon mois/" par-dessus "orégon années/") sans rien casser.
     *
     * Le seul appelant qui lit la valeur de retour, CRangeSyncService::flushBatch,
     * pré-filtre les lignes pour ne passer que des dt absents : de ce chemin, il
     * n'y a donc jamais de collision et le compteur reste exact.
     */
    public function insertMinutelyBatch(array $rows): int
    {
        if (empty($rows)) {
            return 0;
        }

        $q = fn ($v) => $v === null ? 'NULL' : $this->connection->quote((string) $v);

        $values = [];
        foreach ($rows as $row) {
            $values[] = sprintf(
                "(%d, %s, %s, %s, '0', %s, %s, %s, %s, %s, %s)",
                $row['dt'],
                $this->connection->quote((string) $row['temp']),
                $this->connection->quote((string) ($row['pressure'] ?? 0)),
                $this->connection->quote((string) ($row['humidity'] ?? 0)),
                $this->connection->quote((string) ($row['wind_speed'] ?? 0)),
                $this->connection->quote((string) ($row['wind_deg'] ?? 0)),
                // wind_speed_kmh : c'est cette colonne que WeatherHWminutelyCRange::toArray()
                // sérialise sous la clé "wind_speed" ; la source est déjà en km/h.
                $this->connection->quote((string) ($row['wind_speed'] ?? 0)),
                $q($row['rain_rate'] ?? null),
                $q($row['rain_hourly'] ?? null),
                $q($row['rain_accumulated'] ?? null)
            );
        }

        $sql = 'INSERT INTO weather_hwminutely_crange
                    (dt, temp, pressure, humidity, uvi, wind_speed, wind_deg, wind_speed_kmh, rain_rate, rain_hourly, rain_accumulated)
                VALUES ' . implode(',', $values) . '
                ON DUPLICATE KEY UPDATE
                    rain_rate = VALUES(rain_rate),
                    rain_hourly = VALUES(rain_hourly),
                    rain_accumulated = VALUES(rain_accumulated)';

        return (int) $this->connection->executeStatement($sql);
    }

    /** Upsert d'un jour à partir de stats déjà agrégées (CRangeAggregator::finalize). Ne touche jamais sunshine_minutes. */
    public function upsertDaily(int $dayEpoch, array $finalStats): void
    {
        $this->connection->executeStatement(
            'INSERT INTO weather_daily_crange
                (day, temp_avg, temp_max, temp_min, temp_0, temp_12, pressure_avg, pressure_max, pressure_min,
                 humidity_avg, humidity_max, humidity_min, uvi_avg, uvi_max, wind_speed_avg, wind_speed_max, wind_deg_avg,
                 temp_min_dt, temp_max_dt, pressure_min_dt, pressure_max_dt, humidity_min_dt, humidity_max_dt, wind_speed_max_dt,
                 rain_total, rain_rate_avg, rain_rate_max, rain_rate_max_dt, rain_hourly_avg, rain_hourly_max, rain_hourly_max_dt)
             VALUES
                (:day, :temp_avg, :temp_max, :temp_min, :temp_0, :temp_12, :pressure_avg, :pressure_max, :pressure_min,
                 :humidity_avg, :humidity_max, :humidity_min, 0, 0, :wind_speed_avg, :wind_speed_max, :wind_deg_avg,
                 :temp_min_dt, :temp_max_dt, :pressure_min_dt, :pressure_max_dt, :humidity_min_dt, :humidity_max_dt, :wind_speed_max_dt,
                 :rain_total, :rain_rate_avg, :rain_rate_max, :rain_rate_max_dt, :rain_hourly_avg, :rain_hourly_max, :rain_hourly_max_dt)
             ON DUPLICATE KEY UPDATE
                temp_avg = VALUES(temp_avg), temp_max = VALUES(temp_max), temp_min = VALUES(temp_min),
                temp_0 = VALUES(temp_0), temp_12 = VALUES(temp_12),
                pressure_avg = VALUES(pressure_avg), pressure_max = VALUES(pressure_max), pressure_min = VALUES(pressure_min),
                humidity_avg = VALUES(humidity_avg), humidity_max = VALUES(humidity_max), humidity_min = VALUES(humidity_min),
                wind_speed_avg = VALUES(wind_speed_avg), wind_speed_max = VALUES(wind_speed_max), wind_deg_avg = VALUES(wind_deg_avg),
                temp_min_dt = VALUES(temp_min_dt), temp_max_dt = VALUES(temp_max_dt),
                pressure_min_dt = VALUES(pressure_min_dt), pressure_max_dt = VALUES(pressure_max_dt),
                humidity_min_dt = VALUES(humidity_min_dt), humidity_max_dt = VALUES(humidity_max_dt),
                wind_speed_max_dt = VALUES(wind_speed_max_dt),
                rain_total = VALUES(rain_total),
                rain_rate_avg = VALUES(rain_rate_avg), rain_rate_max = VALUES(rain_rate_max), rain_rate_max_dt = VALUES(rain_rate_max_dt),
                rain_hourly_avg = VALUES(rain_hourly_avg), rain_hourly_max = VALUES(rain_hourly_max), rain_hourly_max_dt = VALUES(rain_hourly_max_dt)',
            array_merge(['day' => $dayEpoch], $finalStats)
        );
    }

    /**
     * Recalcule intégralement l'agrégat journalier d'un jour à partir de TOUTES
     * les lignes minutely déjà en base pour ce jour (pas seulement les
     * nouvelles) — utilisé par la synchro incrémentale, où un jour peut avoir
     * été partiellement rempli par un run précédent.
     */
    public function recomputeDay(int $dayEpoch, \DateTimeZone $paris): void
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT dt, temp, humidity, pressure, wind_speed_kmh AS wind_speed, wind_deg,
                    rain_rate, rain_hourly, rain_accumulated
             FROM weather_hwminutely_crange
             WHERE dt >= :start AND dt < :end
             ORDER BY dt ASC',
            ['start' => $dayEpoch, 'end' => $dayEpoch + 86400]
        );

        if (empty($rows)) {
            return;
        }

        $dayStats = [];
        foreach ($rows as $row) {
            CRangeAggregator::accumulate($dayStats, $paris, [
                'dt' => (int) $row['dt'],
                'temp' => (float) $row['temp'],
                'humidity' => $row['humidity'] !== null ? (float) $row['humidity'] : null,
                'pressure' => $row['pressure'] !== null ? (float) $row['pressure'] : null,
                'wind_speed' => $row['wind_speed'] !== null ? (float) $row['wind_speed'] : null,
                'wind_deg' => $row['wind_deg'] !== null ? (float) $row['wind_deg'] : null,
                'rain_rate' => $row['rain_rate'] !== null ? (float) $row['rain_rate'] : null,
                'rain_hourly' => $row['rain_hourly'] !== null ? (float) $row['rain_hourly'] : null,
                'rain_accumulated' => $row['rain_accumulated'] !== null ? (float) $row['rain_accumulated'] : null,
            ]);
        }

        // Une seule clé possible ($dayEpoch aligné sur des bornes UTC de 86400s).
        foreach ($dayStats as $epoch => $stats) {
            $this->upsertDaily($epoch, CRangeAggregator::finalize($stats));
        }
    }
}
