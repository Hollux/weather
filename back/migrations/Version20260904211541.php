<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Crée weather_crange_sync_state : suivi des fichiers déjà traités par la
 * synchro incrémentale (app:crange:sync) sur le dossier Drive "Orégon sauve
 * graph/", pour ne retélécharger/reparser un fichier que si sa date de
 * modification Drive a changé.
 */
final class Version20260904211541 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crée weather_crange_sync_state';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE weather_crange_sync_state (filename VARCHAR(255) NOT NULL, drive_modified_at DATETIME NOT NULL, imported_at DATETIME NOT NULL, rows_inserted INT NOT NULL, PRIMARY KEY(filename)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE weather_crange_sync_state');
    }
}
