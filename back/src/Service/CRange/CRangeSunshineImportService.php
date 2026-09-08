<?php

namespace App\Service\CRange;

use Doctrine\DBAL\Connection;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Exploite les fichiers "Météo <année> Orégon.xls" — des classeurs assemblés
 * à la main (un onglet par mois, une ligne par jour, colonnes maxi/mini par
 * grandeur), distincts des exports "Toutes les données météorologiques" de
 * la station (minutely). Deux usages :
 *
 * - importFile() : n'exploite que la colonne "soleil" (heures décimales, ex:
 *   9,25 = 9h15) pour compléter sunshine_minutes sur des jours qui existent
 *   déjà (créés par l'import minutely). Le reste du classeur est ignoré, ces
 *   valeurs étant déjà couvertes par l'import minutely quand il existe.
 *
 * - importMissingDailySummary() : pour les années sans export minutely (ex:
 *   2019, absent de l'archive Drive "orégon années/"), reconstruit un
 *   weather_daily_crange approximatif à partir des seules colonnes maxi/mini
 *   disponibles (pas de moyenne réelle : temp/pression/humidité moyennes
 *   estimées via (max+min)/2, convention météo standard ; temp_0/temp_12
 *   n'ont pas d'équivalent horaire précis dans ce classeur et retombent sur
 *   cette même moyenne ; wind_speed_avg inconnu -> 0). N'insère QUE les jours
 *   absents de la table (INSERT IGNORE) : ne remplace jamais une ligne issue
 *   d'un vrai import minutely, plus fiable.
 */
class CRangeSunshineImportService
{
    private const WIND_DEGREES = [
        'n' => 0, 'ne' => 45, 'e' => 90, 'se' => 135,
        's' => 180, 'sw' => 225, 'w' => 270, 'nw' => 315,
    ];

    public function __construct(private Connection $connection)
    {
    }

    /** @return array{updated:int,skippedNoRow:int,skippedUnreadable:int} */
    public function importFile(string $filePath): array
    {
        $spreadsheet = $this->load($filePath);
        $updated = 0;
        $skippedNoRow = 0;
        $skippedUnreadable = 0;

        foreach ($this->iterateSheets($spreadsheet) as [$grid, $year, $month]) {
            $soleilCol = $this->findLabelColumn($grid, 'soleil');
            if ($soleilCol === null) {
                continue;
            }

            foreach ($this->iterateDayRows($grid, $year, $month) as [$day, $date, $rowValues]) {
                if ($date === null) {
                    $skippedUnreadable++;
                    continue;
                }

                $hours = CRangeValueParser::parseValue($rowValues[$soleilCol] ?? null);
                if ($hours === null) {
                    continue; // "?", vide, ou non renseigné ce jour-là
                }

                $dayEpoch = intdiv($date->getTimestamp(), 86400) * 86400;
                $minutes = (int) round($hours * 60);

                $affected = $this->connection->executeStatement(
                    'UPDATE weather_daily_crange SET sunshine_minutes = :minutes WHERE day = :day',
                    ['minutes' => $minutes, 'day' => $dayEpoch]
                );

                if ($affected > 0) {
                    $updated++;
                } else {
                    $skippedNoRow++;
                }
            }
        }

        $spreadsheet->disconnectWorksheets();

        return ['updated' => $updated, 'skippedNoRow' => $skippedNoRow, 'skippedUnreadable' => $skippedUnreadable];
    }

    /** @return array{inserted:int,skippedExisting:int,skippedUnreadable:int} */
    public function importMissingDailySummary(string $filePath): array
    {
        $spreadsheet = $this->load($filePath);
        $inserted = 0;
        $skippedExisting = 0;
        $skippedUnreadable = 0;

        foreach ($this->iterateSheets($spreadsheet) as [$grid, $year, $month]) {
            $baroCol = $this->findLabelColumn($grid, 'baro.') ?? $this->findLabelColumn($grid, 'baro');
            $tempCol = $this->findLabelColumn($grid, 'températures sonde 1');
            $hygroCol = $this->findLabelColumn($grid, 'hygro');
            $ventCol = $this->findLabelColumn($grid, 'vent maxi');
            $soleilCol = $this->findLabelColumn($grid, 'soleil');

            if ($tempCol === null) {
                continue; // onglet sans en-tête exploitable (ex: "Rapport sur la compatibilité")
            }

            foreach ($this->iterateDayRows($grid, $year, $month) as [$day, $date, $rowValues]) {
                if ($date === null) {
                    $skippedUnreadable++;
                    continue;
                }

                $tempMax = CRangeValueParser::parseValue($rowValues[$tempCol] ?? null);
                $tempMin = CRangeValueParser::parseValue($rowValues[$tempCol + 2] ?? null);
                if ($tempMax === null || $tempMin === null) {
                    continue; // sans température, le jour n'est pas exploitable
                }
                $tempAvg = round(($tempMax + $tempMin) / 2, 2);

                $pressureMax = $baroCol !== null ? CRangeValueParser::parseValue($rowValues[$baroCol] ?? null) : null;
                $pressureMin = $baroCol !== null ? CRangeValueParser::parseValue($rowValues[$baroCol + 1] ?? null) : null;
                $humidityMax = $hygroCol !== null ? CRangeValueParser::parseValue($rowValues[$hygroCol] ?? null) : null;
                $humidityMin = $hygroCol !== null ? CRangeValueParser::parseValue($rowValues[$hygroCol + 2] ?? null) : null;
                $windSpeedMax = $ventCol !== null ? CRangeValueParser::parseValue($rowValues[$ventCol] ?? null) : null;
                $windDegRaw = $ventCol !== null ? ($rowValues[$ventCol + 2] ?? null) : null;
                $sunshineHours = $soleilCol !== null ? CRangeValueParser::parseValue($rowValues[$soleilCol] ?? null) : null;

                $dayEpoch = intdiv($date->getTimestamp(), 86400) * 86400;

                $affected = $this->connection->executeStatement(
                    'INSERT IGNORE INTO weather_daily_crange
                        (day, temp_avg, temp_max, temp_min, temp_0, temp_12, pressure_avg, pressure_max, pressure_min,
                         humidity_avg, humidity_max, humidity_min, uvi_avg, uvi_max, wind_speed_avg, wind_speed_max, wind_deg_avg, sunshine_minutes)
                     VALUES
                        (:day, :temp_avg, :temp_max, :temp_min, :temp_avg, :temp_avg, :pressure_avg, :pressure_max, :pressure_min,
                         :humidity_avg, :humidity_max, :humidity_min, 0, 0, 0, :wind_speed_max, :wind_deg_avg, :sunshine_minutes)',
                    [
                        'day' => $dayEpoch,
                        'temp_avg' => $tempAvg,
                        'temp_max' => $tempMax,
                        'temp_min' => $tempMin,
                        'pressure_avg' => $pressureMax !== null && $pressureMin !== null ? round(($pressureMax + $pressureMin) / 2, 2) : 0,
                        'pressure_max' => $pressureMax ?? 0,
                        'pressure_min' => $pressureMin ?? 0,
                        'humidity_avg' => $humidityMax !== null && $humidityMin !== null ? round(($humidityMax + $humidityMin) / 2, 2) : 0,
                        'humidity_max' => $humidityMax ?? 0,
                        'humidity_min' => $humidityMin ?? 0,
                        'wind_speed_max' => $windSpeedMax ?? 0,
                        'wind_deg_avg' => $this->parseWindDirection($windDegRaw) ?? 0,
                        'sunshine_minutes' => $sunshineHours !== null ? (int) round($sunshineHours * 60) : null,
                    ]
                );

                if ($affected > 0) {
                    $inserted++;
                } else {
                    $skippedExisting++;
                }
            }
        }

        $spreadsheet->disconnectWorksheets();

        return ['inserted' => $inserted, 'skippedExisting' => $skippedExisting, 'skippedUnreadable' => $skippedUnreadable];
    }

    private function load(string $filePath): Spreadsheet
    {
        $reader = IOFactory::createReaderForFile($filePath);
        $reader->setReadDataOnly(true);
        if (method_exists($reader, 'setReadEmptyCells')) {
            $reader->setReadEmptyCells(false);
        }

        return $reader->load($filePath);
    }

    /** @return \Generator<array{0:array,1:int,2:int}> [grid, year, month] par onglet exploitable */
    private function iterateSheets(Spreadsheet $spreadsheet): \Generator
    {
        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            $highestRow = $sheet->getHighestDataRow();
            $highestCol = $sheet->getHighestDataColumn();
            if ($highestRow < 2) {
                continue;
            }

            $grid = $sheet->rangeToArray('A1:' . $highestCol . $highestRow, null, true, false);

            $year = null;
            $month = null;
            foreach ($grid[0] ?? [] as $cell) {
                if (is_string($cell) && preg_match('/(\d{1,2})\/(\d{4})/', $cell, $m)) {
                    $month = (int) $m[1];
                    $year = (int) $m[2];
                    break;
                }
            }
            if ($year === null || $month === null || $month < 1 || $month > 12) {
                continue; // onglet sans en-tête exploitable (ex: "Rapport sur la compatibilité")
            }

            yield [$grid, $year, $month];
        }
    }

    /** Cherche une cellule texte (trim, insensible à la casse) et renvoie son index de colonne. */
    private function findLabelColumn(array $grid, string $label): ?int
    {
        foreach ($grid as $rowValues) {
            foreach ($rowValues as $colIdx => $cell) {
                if (is_string($cell) && strtolower(trim($cell)) === $label) {
                    return $colIdx;
                }
            }
        }

        return null;
    }

    /** @return \Generator<array{0:int,1:?\DateTime,2:array}> [day, date Paris midi (ou null si invalide), rowValues] */
    private function iterateDayRows(array $grid, int $year, int $month): \Generator
    {
        $paris = new \DateTimeZone('Europe/Paris');

        foreach ($grid as $rowValues) {
            $day = $rowValues[0] ?? null;
            if (!is_numeric($day) || (int) $day < 1 || (int) $day > 31) {
                continue; // pas une ligne de données (en-têtes, séparateurs...)
            }

            $date = \DateTime::createFromFormat('Y-n-j H:i', "$year-$month-{$day} 12:00", $paris);
            if (!$date || (int) $date->format('j') !== (int) $day) {
                yield [(int) $day, null, $rowValues];
                continue;
            }

            yield [(int) $day, $date, $rowValues];
        }
    }

    /** Convertit une direction abrégée ("s", "w/nw", "n/nw"...) en degrés, via moyenne circulaire. */
    private function parseWindDirection(mixed $raw): ?float
    {
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }

        $degrees = [];
        foreach (explode('/', strtolower(trim($raw))) as $token) {
            if (isset(self::WIND_DEGREES[$token])) {
                $degrees[] = self::WIND_DEGREES[$token];
            }
        }
        if (empty($degrees)) {
            return null;
        }

        $sin = array_sum(array_map(fn ($d) => sin(deg2rad($d)), $degrees));
        $cos = array_sum(array_map(fn ($d) => cos(deg2rad($d)), $degrees));
        $mean = rad2deg(atan2($sin, $cos));

        return round($mean < 0 ? $mean + 360 : $mean, 1);
    }
}
