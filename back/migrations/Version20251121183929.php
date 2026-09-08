<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251121183929 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE weather_hwminutely (id INT AUTO_INCREMENT NOT NULL, dt INT NOT NULL, temp VARCHAR(8) NOT NULL, pressure VARCHAR(8) NOT NULL, humidity VARCHAR(8) NOT NULL, uvi VARCHAR(8) NOT NULL, wind_speed VARCHAR(8) NOT NULL, wind_deg VARCHAR(8) NOT NULL, wind_speed_kmh VARCHAR(8) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        // weather_ville est déjà créée par Version20211002051749 ; ce doublon
        // faisait échouer toute migration à partir d'une base vide.
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE weather_hwminutely');
    }
}
