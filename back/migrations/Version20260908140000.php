<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * weather_hwminutely_crange : ajoute les colonnes pluie de l'export station
 * Orégon WMR300 (absentes de l'API OpenWeatherMap, donc station CRange
 * uniquement) :
 *  - rain_rate         = "Pluviométrie(mm/h)"           (mm/h instantané)
 *  - rain_hourly       = "Chute de pluie par heure (mm)" (mm sur l'heure glissante)
 *  - rain_accumulated  = "Chute de pluie accumulée (mm)" (odomètre station, reset manuel)
 *
 * Colonnes nullables, pas de valeur par défaut : elles restent NULL sur les
 * lignes existantes jusqu'au ré-import des fichiers "orégon mois/" (2014-09 →
 * aujourd'hui), qui les renseigne via INSERT ... ON DUPLICATE KEY UPDATE des
 * seules colonnes pluie (voir CRangeWriter::insertMinutelyBatch).
 */
final class Version20260908140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'weather_hwminutely_crange : colonnes pluie (rain_rate / rain_hourly / rain_accumulated)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE weather_hwminutely_crange
            ADD rain_rate VARCHAR(8) DEFAULT NULL,
            ADD rain_hourly VARCHAR(8) DEFAULT NULL,
            ADD rain_accumulated VARCHAR(12) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE weather_hwminutely_crange
            DROP rain_rate,
            DROP rain_hourly,
            DROP rain_accumulated');
    }
}
