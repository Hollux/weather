<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * weather_daily : normalisation de la colonne `day` sur minuit UTC + dédoublonnage
 * + contrainte d'unicité.
 *
 * Contexte : `day` était écrit à minuit Europe/Paris alors que la détection des jours
 * manquants comparait des dates UTC -> décalage d'un jour -> réinsertion permanente
 * des mêmes jours (doublons). On réaligne tout sur FLOOR(dt/86400)*86400 (minuit UTC),
 * on supprime les doublons (on garde la ligne au plus petit id) puis on verrouille
 * avec un index unique.
 */
final class Version20260904115000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'weather_daily: normalise day sur minuit UTC, déduplique et ajoute un index unique sur day';
    }

    public function up(Schema $schema): void
    {
        // 1. Réaligne chaque `day` sur minuit UTC du jour concerné.
        //    +43200 (12 h) puis troncature au jour : robuste au décalage horaire et au DST.
        $this->addSql('UPDATE weather_daily SET day = FLOOR((day + 43200) / 86400) * 86400');

        // 2. Déduplique : conserve la ligne au plus petit id pour chaque `day`.
        $this->addSql(
            'DELETE t1 FROM weather_daily t1 '
            . 'INNER JOIN weather_daily t2 ON t1.day = t2.day AND t1.id > t2.id'
        );

        // 3. Verrouille l'unicité (si pas déjà présent).
        $exists = (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM information_schema.statistics "
            . "WHERE table_schema = DATABASE() AND table_name = 'weather_daily' AND index_name = 'uniq_day'"
        );
        if ($exists === 0) {
            $this->addSql('ALTER TABLE weather_daily ADD UNIQUE INDEX uniq_day (day)');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE weather_daily DROP INDEX uniq_day');
        // La normalisation des valeurs `day` et le dédoublonnage ne sont pas réversibles.
    }
}
