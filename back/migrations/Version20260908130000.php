<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * weather_daily : ajoute l'heure (timestamp de la mesure minutely) des mini / maxi
 * du jour, pour l'afficher sur le graphe journalier de l'API. Les moyennes n'ont
 * pas d'heure : c'est la journée.
 *
 * L'info n'existait pas en base : on la reconstitue en croisant weather_daily avec
 * weather_hwminutely (source API uniquement — la station CRange a sa propre
 * migration, voir Version20260908130100). Un jour sans minutely (antérieur au
 * suivi, ou trou de collecte) garde des colonnes NULL : le front retombe alors sur
 * l'affichage « jour seul ».
 *
 * Astuce de backfill : GROUP_CONCAT(dt ORDER BY <valeur>) puis SUBSTRING_INDEX(..,1)
 * = le dt du premier extrême. La troncature éventuelle de group_concat porte sur la
 * fin de la liste, jamais sur le premier élément ; group_concat_max_len est tout de
 * même relevé par sécurité.
 */
final class Version20260908130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'weather_daily : colonnes *_dt (heure des mini/maxi) + backfill depuis weather_hwminutely';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE weather_daily
            ADD temp_min_dt INT DEFAULT NULL,
            ADD temp_max_dt INT DEFAULT NULL,
            ADD pressure_min_dt INT DEFAULT NULL,
            ADD pressure_max_dt INT DEFAULT NULL,
            ADD humidity_min_dt INT DEFAULT NULL,
            ADD humidity_max_dt INT DEFAULT NULL,
            ADD uvi_max_dt INT DEFAULT NULL,
            ADD wind_speed_max_dt INT DEFAULT NULL');

        $this->addSql('SET SESSION group_concat_max_len = 1000000');

        // temp / pressure / humidity : mini + maxi
        foreach (['temp', 'pressure', 'humidity'] as $metric) {
            $this->addSql("
                UPDATE weather_daily d
                JOIN (
                    SELECT FLOOR(h.dt / 86400) * 86400 AS day_epoch,
                        CAST(SUBSTRING_INDEX(GROUP_CONCAT(h.dt ORDER BY CAST(h.$metric AS DECIMAL(12,4)) ASC,  h.dt ASC), ',', 1) AS UNSIGNED) AS min_dt,
                        CAST(SUBSTRING_INDEX(GROUP_CONCAT(h.dt ORDER BY CAST(h.$metric AS DECIMAL(12,4)) DESC, h.dt ASC), ',', 1) AS UNSIGNED) AS max_dt
                    FROM weather_hwminutely h
                    WHERE h.$metric IS NOT NULL AND h.$metric <> ''
                    GROUP BY day_epoch
                ) x ON x.day_epoch = d.day
                SET d.{$metric}_min_dt = x.min_dt,
                    d.{$metric}_max_dt = x.max_dt
            ");
        }

        // uvi : maxi seul
        $this->addSql("
            UPDATE weather_daily d
            JOIN (
                SELECT FLOOR(h.dt / 86400) * 86400 AS day_epoch,
                    CAST(SUBSTRING_INDEX(GROUP_CONCAT(h.dt ORDER BY CAST(h.uvi AS DECIMAL(12,4)) DESC, h.dt ASC), ',', 1) AS UNSIGNED) AS max_dt
                FROM weather_hwminutely h
                WHERE h.uvi IS NOT NULL AND h.uvi <> ''
                GROUP BY day_epoch
            ) x ON x.day_epoch = d.day
            SET d.uvi_max_dt = x.max_dt
        ");

        // wind : maxi seul, sur wind_speed_kmh (c'est la valeur agrégée / affichée)
        $this->addSql("
            UPDATE weather_daily d
            JOIN (
                SELECT FLOOR(h.dt / 86400) * 86400 AS day_epoch,
                    CAST(SUBSTRING_INDEX(GROUP_CONCAT(h.dt ORDER BY CAST(h.wind_speed_kmh AS DECIMAL(12,4)) DESC, h.dt ASC), ',', 1) AS UNSIGNED) AS max_dt
                FROM weather_hwminutely h
                WHERE h.wind_speed_kmh IS NOT NULL AND h.wind_speed_kmh <> ''
                GROUP BY day_epoch
            ) x ON x.day_epoch = d.day
            SET d.wind_speed_max_dt = x.max_dt
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE weather_daily
            DROP temp_min_dt,
            DROP temp_max_dt,
            DROP pressure_min_dt,
            DROP pressure_max_dt,
            DROP humidity_min_dt,
            DROP humidity_max_dt,
            DROP uvi_max_dt,
            DROP wind_speed_max_dt');
    }
}
