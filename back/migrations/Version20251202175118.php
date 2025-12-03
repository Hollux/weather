<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251202175118 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE weather_daily (id INT AUTO_INCREMENT NOT NULL, day DATETIME NOT NULL, temp_avg DOUBLE PRECISION NOT NULL, temp_max DOUBLE PRECISION NOT NULL, temp_min DOUBLE PRECISION NOT NULL, temp_0 DOUBLE PRECISION NOT NULL, temp_12 DOUBLE PRECISION NOT NULL, pressure_avg DOUBLE PRECISION NOT NULL, pressure_max DOUBLE PRECISION NOT NULL, pressure_min DOUBLE PRECISION NOT NULL, humidity_avg DOUBLE PRECISION NOT NULL, humidity_max DOUBLE PRECISION NOT NULL, humidity_min DOUBLE PRECISION NOT NULL, uvi_avg DOUBLE PRECISION NOT NULL, uvi_max DOUBLE PRECISION NOT NULL, wind_speed_avg DOUBLE PRECISION NOT NULL, wind_speed_max DOUBLE PRECISION NOT NULL, wind_deg_avg DOUBLE PRECISION NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE weather_daily');
    }
}
