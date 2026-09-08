<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * weather_daily_crange : ajoute l'heure (timestamp de la mesure minutely) des
 * mini / maxi du jour, pour le graphe journalier CRange. Mirroir de
 * Version20260908130000 mais sur la source CRange, sans mélanger les deux :
 * backfill depuis weather_hwminutely_crange, et pas de colonne UV (la station
 * CRange n'a pas de capteur UV).
 *
 * Cohérence avec CRangeWriter::recomputeDay : on prend les valeurs de la table
 * minutely telles quelles (une valeur "0" issue d'une source sans capteur est
 * traitée comme une mesure, exactement comme le fait recomputeDay). Un jour sans
 * aucune ligne minutely (agrégat importé depuis un résumé journalier) garde des
 * colonnes NULL.
 */
final class Version20260908130100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'weather_daily_crange : colonnes *_dt (heure des mini/maxi) + backfill depuis weather_hwminutely_crange';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE weather_daily_crange
            ADD temp_min_dt INT DEFAULT NULL,
            ADD temp_max_dt INT DEFAULT NULL,
            ADD pressure_min_dt INT DEFAULT NULL,
            ADD pressure_max_dt INT DEFAULT NULL,
            ADD humidity_min_dt INT DEFAULT NULL,
            ADD humidity_max_dt INT DEFAULT NULL,
            ADD wind_speed_max_dt INT DEFAULT NULL');

        $this->addSql('SET SESSION group_concat_max_len = 1000000');

        // temp / pressure / humidity : mini + maxi
        foreach (['temp', 'pressure', 'humidity'] as $metric) {
            $this->addSql("
                UPDATE weather_daily_crange d
                JOIN (
                    SELECT FLOOR(h.dt / 86400) * 86400 AS day_epoch,
                        CAST(SUBSTRING_INDEX(GROUP_CONCAT(h.dt ORDER BY CAST(h.$metric AS DECIMAL(12,4)) ASC,  h.dt ASC), ',', 1) AS UNSIGNED) AS min_dt,
                        CAST(SUBSTRING_INDEX(GROUP_CONCAT(h.dt ORDER BY CAST(h.$metric AS DECIMAL(12,4)) DESC, h.dt ASC), ',', 1) AS UNSIGNED) AS max_dt
                    FROM weather_hwminutely_crange h
                    WHERE h.$metric IS NOT NULL AND h.$metric <> ''
                    GROUP BY day_epoch
                ) x ON x.day_epoch = d.day
                SET d.{$metric}_min_dt = x.min_dt,
                    d.{$metric}_max_dt = x.max_dt
            ");
        }

        // wind : maxi seul, sur wind_speed_kmh
        $this->addSql("
            UPDATE weather_daily_crange d
            JOIN (
                SELECT FLOOR(h.dt / 86400) * 86400 AS day_epoch,
                    CAST(SUBSTRING_INDEX(GROUP_CONCAT(h.dt ORDER BY CAST(h.wind_speed_kmh AS DECIMAL(12,4)) DESC, h.dt ASC), ',', 1) AS UNSIGNED) AS max_dt
                FROM weather_hwminutely_crange h
                WHERE h.wind_speed_kmh IS NOT NULL AND h.wind_speed_kmh <> ''
                GROUP BY day_epoch
            ) x ON x.day_epoch = d.day
            SET d.wind_speed_max_dt = x.max_dt
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE weather_daily_crange
            DROP temp_min_dt,
            DROP temp_max_dt,
            DROP pressure_min_dt,
            DROP pressure_max_dt,
            DROP humidity_min_dt,
            DROP humidity_max_dt,
            DROP wind_speed_max_dt');
    }
}
