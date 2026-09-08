<?php

namespace App\Service\CRange;

/**
 * Import complet d'un fichier d'export CRange (toutes les lignes du fichier,
 * peu importe si déjà présentes en base — idempotent grâce à INSERT ... ON
 * DUPLICATE KEY UPDATE). Utilisé pour un import manuel ponctuel ou pour le
 * backfill depuis l'archive Drive.
 *
 * importFile() agrège le jour à partir des seules lignes du fichier : suffisant
 * pour des fichiers annuels (une seule frontière de jour, le 31/12). Pour des
 * fichiers mensuels, où chaque frontière de mois tombe sur un jour à cheval
 * (minuit UTC), utiliser importDirectory() : il insère d'abord toutes les
 * lignes minutely, puis recalcule chaque jour touché à partir de TOUTE la base
 * (CRangeWriter::recomputeDay) — pas d'agrégat de jour de bord écrasé par un
 * bout de fichier voisin.
 */
class CRangeImportService
{
    private const BATCH_SIZE = 1000;

    public function __construct(
        private CRangeFileReader $reader,
        private CRangeWriter $writer,
    ) {
    }

    /**
     * Import de tous les fichiers .xls/.xlsx d'un dossier en deux temps :
     * 1) insertion de toutes les lignes minutely (ON DUPLICATE KEY UPDATE : les
     *    colonnes pluie sont renseignées sur les lignes déjà présentes) ;
     * 2) recalcul de l'agrégat journalier de chaque jour touché depuis la base
     *    complète.
     *
     * @param callable(string,int):void|null $onFile appelé après chaque fichier (basename, lignes)
     *
     * @return array{files:int,rows:int,days:int}
     */
    public function importDirectory(string $dir, ?callable $onFile = null): array
    {
        $files = glob(rtrim($dir, '/') . '/*.xls*') ?: [];
        sort($files);

        $paris = new \DateTimeZone('Europe/Paris');
        $touchedDays = [];
        $totalRows = 0;

        foreach ($files as $file) {
            $batch = [];
            $fileRows = 0;
            foreach ($this->reader->read($file) as $row) {
                $batch[] = $row;
                $touchedDays[intdiv($row['dt'], 86400) * 86400] = true;
                $fileRows++;

                if (count($batch) >= self::BATCH_SIZE) {
                    $this->writer->insertMinutelyBatch($batch);
                    $batch = [];
                }
            }
            if (!empty($batch)) {
                $this->writer->insertMinutelyBatch($batch);
            }

            $totalRows += $fileRows;
            if ($onFile) {
                $onFile(basename($file), $fileRows);
            }
        }

        foreach (array_keys($touchedDays) as $dayEpoch) {
            $this->writer->recomputeDay($dayEpoch, $paris);
        }

        return ['files' => count($files), 'rows' => $totalRows, 'days' => count($touchedDays)];
    }

    /** @return array{rows:int,days:int} */
    public function importFile(string $filePath): array
    {
        $paris = new \DateTimeZone('Europe/Paris');
        $dayStats = [];
        $batch = [];
        $rowCount = 0;

        foreach ($this->reader->read($filePath) as $row) {
            $batch[] = $row;
            CRangeAggregator::accumulate($dayStats, $paris, $row);
            $rowCount++;

            if (count($batch) >= self::BATCH_SIZE) {
                $this->writer->insertMinutelyBatch($batch);
                $batch = [];
            }
        }
        if (!empty($batch)) {
            $this->writer->insertMinutelyBatch($batch);
        }

        foreach ($dayStats as $dayEpoch => $stats) {
            $this->writer->upsertDaily($dayEpoch, CRangeAggregator::finalize($stats));
        }

        return ['rows' => $rowCount, 'days' => count($dayStats)];
    }
}
