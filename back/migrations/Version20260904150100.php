<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Crée weather_daily_crange : agrégats journaliers de la station CRange, mirroir
 * de weather_daily (voir WeatherDailyCRange) + `sunshine_minutes` (durée
 * d'ensoleillement du jour, saisie manuelle via la page "Luminosité Journalière",
 * NULL = non saisi) car cette station n'a pas de capteur de luminosité/UV.
 */
final class Version20260904150100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crée weather_daily_crange';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE weather_daily_crange (id INT AUTO_INCREMENT NOT NULL, day INT NOT NULL, temp_avg DOUBLE PRECISION NOT NULL, temp_max DOUBLE PRECISION NOT NULL, temp_min DOUBLE PRECISION NOT NULL, temp_0 DOUBLE PRECISION NOT NULL, temp_12 DOUBLE PRECISION NOT NULL, pressure_avg DOUBLE PRECISION NOT NULL, pressure_max DOUBLE PRECISION NOT NULL, pressure_min DOUBLE PRECISION NOT NULL, humidity_avg DOUBLE PRECISION NOT NULL, humidity_max DOUBLE PRECISION NOT NULL, humidity_min DOUBLE PRECISION NOT NULL, uvi_avg DOUBLE PRECISION NOT NULL, uvi_max DOUBLE PRECISION NOT NULL, wind_speed_avg DOUBLE PRECISION NOT NULL, wind_speed_max DOUBLE PRECISION NOT NULL, wind_deg_avg DOUBLE PRECISION NOT NULL, sunshine_minutes INT DEFAULT NULL, UNIQUE INDEX uniq_crange_day (day), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE weather_daily_crange');
    }
}
