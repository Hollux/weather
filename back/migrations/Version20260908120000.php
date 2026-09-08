<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Purge les lignes de weather_crange_sync_state qui n'ont rien importé
 * (rows_inserted = 0). Avant le correctif de CRangeSyncService, le bouton
 * "Maj CRange" était incrémental « vers l'avant » : un fichier de backfill
 * (ex. les exports 2019 déposés dans "Orégon sauve graph/") était marqué
 * comme lu sans qu'aucune ligne ne soit insérée, ce qui le rendait ensuite
 * définitivement ignoré. On efface ces états sans effet pour que ces
 * fichiers soient rejoués une fois (l'import est idempotent : pré-filtre
 * sur `dt` + INSERT IGNORE).
 */
final class Version20260908120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Purge les états weather_crange_sync_state sans import (rows_inserted = 0) pour rejouer les fichiers de backfill";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DELETE FROM weather_crange_sync_state WHERE rows_inserted = 0');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Suppression de lignes de suivi, non réversible.');
    }
}
