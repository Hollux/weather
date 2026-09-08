<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Crée weather_hwminutely_crange : mesures brutes de la station CRange, mirroir
 * de weather_hwminutely (voir WeatherHWminutelyCRange) mais avec un index unique
 * sur `dt` car les fichiers exportés contiennent tout l'historique à chaque
 * import (import idempotent, cf. CRangeImportCommand).
 */
final class Version20260904150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crée weather_hwminutely_crange';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE weather_hwminutely_crange (id INT AUTO_INCREMENT NOT NULL, dt INT NOT NULL, temp VARCHAR(8) NOT NULL, pressure VARCHAR(8) NOT NULL, humidity VARCHAR(8) NOT NULL, uvi VARCHAR(8) NOT NULL, wind_speed VARCHAR(8) NOT NULL, wind_deg VARCHAR(8) NOT NULL, wind_speed_kmh VARCHAR(8) DEFAULT NULL, UNIQUE INDEX uniq_crange_dt (dt), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE weather_hwminutely_crange');
    }
}
