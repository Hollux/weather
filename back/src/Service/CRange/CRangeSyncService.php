<?php

namespace App\Service\CRange;

use Doctrine\DBAL\Connection;

/**
 * Synchro incrémentale CRange. Deux garde-fous, et deux seulement :
 *
 *  1. un fichier dont le nom figure déjà dans weather_crange_sync_state est
 *     ignoré (il a déjà été lu une fois — on ne le reparse jamais) ;
 *  2. pour un fichier encore inconnu, on n'insère QUE les `dt` absents de
 *     weather_hwminutely_crange (pré-filtre explicite + INSERT IGNORE en
 *     filet), puis on recalcule uniquement les jours ayant reçu une ligne.
 *
 * Il n'y a PAS de filtre "postérieur à la dernière mesure en base" : un
 * nouveau fichier couvrant une période ancienne (backfill d'un trou
 * historique déposé dans le dossier Drive) est donc importé normalement.
 *
 * Voir CRangeSyncCommand (usage CLI, dossier local) et CRangeController::sync
 * (déclenchement web, dossier alimenté depuis Drive par CRangeDriveClient).
 */
class CRangeSyncService
{
    public function __construct(
        private Connection $connection,
        private CRangeFileReader $reader,
        private CRangeWriter $writer,
    ) {
    }

    /** @return array{files:array<int,array{filename:string,status:string,rowsInserted:int}>,totalRowsInserted:int,daysRecomputed:int} */
    public function syncDirectory(string $dir): array
    {
        $paris = new \DateTimeZone('Europe/Paris');
        $files = glob(rtrim($dir, '/') . '/*.xls*');
        sort($files);

        $result = ['files' => [], 'totalRowsInserted' => 0, 'daysRecomputed' => 0];
        $daysRecomputed = [];

        foreach ($files as $file) {
            $filename = basename($file);

            // Garde-fou 1 : nom de fichier déjà lu -> on ignore, toujours.
            $alreadyRead = (bool) $this->connection->fetchOne(
                'SELECT 1 FROM weather_crange_sync_state WHERE filename = :filename',
                ['filename' => $filename]
            );
            if ($alreadyRead) {
                $result['files'][] = ['filename' => $filename, 'status' => 'unchanged', 'rowsInserted' => 0];
                continue;
            }

            $mtime = (new \DateTime('@' . filemtime($file)))->setTimezone($paris);

            $batch = [];
            $touchedDays = [];
            $rowsInserted = 0;
            foreach ($this->reader->read($file) as $row) {
                $batch[] = $row;

                if (count($batch) >= 1000) {
                    $rowsInserted += $this->flushBatch($batch, $touchedDays);
                    $batch = [];
                }
            }
            if (!empty($batch)) {
                $rowsInserted += $this->flushBatch($batch, $touchedDays);
            }

            foreach (array_keys($touchedDays) as $dayEpoch) {
                $this->writer->recomputeDay($dayEpoch, $paris);
                $daysRecomputed[$dayEpoch] = true;
            }

            $this->connection->executeStatement(
                'INSERT INTO weather_crange_sync_state (filename, drive_modified_at, imported_at, rows_inserted)
                 VALUES (:filename, :modified, NOW(), :rows)
                 ON DUPLICATE KEY UPDATE drive_modified_at = VALUES(drive_modified_at), imported_at = VALUES(imported_at), rows_inserted = VALUES(rows_inserted)',
                ['filename' => $filename, 'modified' => $mtime->format('Y-m-d H:i:s'), 'rows' => $rowsInserted]
            );

            $result['files'][] = ['filename' => $filename, 'status' => 'new', 'rowsInserted' => $rowsInserted];
            $result['totalRowsInserted'] += $rowsInserted;
        }

        $result['daysRecomputed'] = count($daysRecomputed);

        return $result;
    }

    /**
     * Insère les lignes du lot dont le `dt` n'est pas déjà en base (garde-fou 2 :
     * l'ajout ne se fait QUE sur des timestamps non renseignés) et marque pour
     * recalcul les seuls jours ayant effectivement reçu une nouvelle ligne.
     *
     * @param array<int,array{dt:int,temp:float,humidity:?float,pressure:?float,wind_speed:?float,wind_deg:?float}> $batch
     * @param array<int,true>                                                                                       $touchedDays passé par référence
     *
     * @return int nombre de lignes réellement insérées
     */
    private function flushBatch(array $batch, array &$touchedDays): int
    {
        $dts = array_column($batch, 'dt');
        $placeholders = implode(',', array_fill(0, count($dts), '?'));
        $existing = array_flip($this->connection->fetchFirstColumn(
            "SELECT dt FROM weather_hwminutely_crange WHERE dt IN ($placeholders)",
            $dts
        ));

        $fresh = array_values(array_filter($batch, static fn ($row) => !isset($existing[$row['dt']])));
        if (empty($fresh)) {
            return 0;
        }

        $this->writer->insertMinutelyBatch($fresh);

        foreach ($fresh as $row) {
            $touchedDays[intdiv($row['dt'], 86400) * 86400] = true;
        }

        return count($fresh);
    }
}
