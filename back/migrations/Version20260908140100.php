<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * weather_daily_crange : agrégats journaliers de pluie (station CRange
 * uniquement). Miroir "daily" de Version20260908140000.
 *
 *  - rain_total          : mm tombés dans la journée = rain_accumulated du
 *                          dernier relevé du jour moins celui du premier
 *                          (repli sur la somme des incréments positifs si
 *                          l'odomètre a été remis à zéro dans la journée).
 *  - rain_rate_avg/max    : "Pluviométrie(mm/h)" -> moyenne / maxi du jour
 *  - rain_rate_max_dt     : horodatage (dt minutely) du maxi de rain_rate
 *  - rain_hourly_avg/max  : "Chute de pluie par heure (mm)" -> moyenne / maxi
 *  - rain_hourly_max_dt   : horodatage (dt minutely) du maxi de rain_hourly
 *
 * Toutes nullables : NULL = jour sans donnée de pluie (fichiers antérieurs aux
 * colonnes pluie de l'export). Renseignées par le ré-import de "orégon mois/"
 * via CRangeAggregator + CRangeWriter::upsertDaily. Pas de backfill SQL ici :
 * il faut relire les fichiers minutely.
 */
final class Version20260908140100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'weather_daily_crange : agrégats journaliers de pluie (rain_total / rain_rate_* / rain_hourly_*)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE weather_daily_crange
            ADD rain_total DOUBLE PRECISION DEFAULT NULL,
            ADD rain_rate_avg DOUBLE PRECISION DEFAULT NULL,
            ADD rain_rate_max DOUBLE PRECISION DEFAULT NULL,
            ADD rain_rate_max_dt INT DEFAULT NULL,
            ADD rain_hourly_avg DOUBLE PRECISION DEFAULT NULL,
            ADD rain_hourly_max DOUBLE PRECISION DEFAULT NULL,
            ADD rain_hourly_max_dt INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE weather_daily_crange
            DROP rain_total,
            DROP rain_rate_avg,
            DROP rain_rate_max,
            DROP rain_rate_max_dt,
            DROP rain_hourly_avg,
            DROP rain_hourly_max,
            DROP rain_hourly_max_dt');
    }
}
