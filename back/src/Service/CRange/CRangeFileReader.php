<?php

namespace App\Service\CRange;

use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Lit un fichier d'export minutely de la station CRange (Orégon WMR300) et
 * produit des lignes normalisées, quel que soit le format réel du fichier :
 *
 * - texte UTF-16LE tabulé ("Toutes les données météorologiques_*.xls" exporté
 *   tel quel depuis le logiciel de la station) ;
 * - XLSX réel (mêmes exports ré-enregistrés depuis Excel/LibreOffice) ;
 * - XLS BIFF/OLE2 réel (vieux fichiers de "orégon mois/" antérieurs à 2015).
 *
 * Chaque ligne produite : ['dt' => int, 'temp' => float, 'humidity' => ?float,
 * 'pressure' => ?float, 'wind_speed' => ?float, 'wind_deg' => ?float,
 * 'rain_rate' => ?float, 'rain_hourly' => ?float, 'rain_accumulated' => ?float].
 * Les lignes sans température exploitable sont omises (comme dans l'import
 * d'origine : sans temp, la ligne n'est pas utilisable). Une pluie à 0.00 est
 * une vraie mesure et n'est jamais filtrée.
 */
class CRangeFileReader
{
    /** @return \Generator<array{dt:int,temp:float,humidity:?float,pressure:?float,wind_speed:?float,wind_deg:?float,rain_rate:?float,rain_hourly:?float,rain_accumulated:?float}> */
    public function read(string $filePath): \Generator
    {
        if ($this->isUtf16TabText($filePath)) {
            yield from $this->readTabText($filePath);
        } else {
            yield from $this->readSpreadsheet($filePath);
        }
    }

    private function isUtf16TabText(string $filePath): bool
    {
        $handle = fopen($filePath, 'rb');
        $bom = fread($handle, 8);
        fclose($handle);

        // XLSX : signature ZIP "PK\x03\x04". XLS BIFF : en-tête OLE2
        // "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1". Ces deux formats passent par
        // PhpSpreadsheet. Tout le reste = texte tabulé UTF-16LE (BOM FF FE),
        // format natif de la station.
        return !str_starts_with($bom, "PK\x03\x04")
            && !str_starts_with($bom, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1");
    }

    private function readTabText(string $filePath): \Generator
    {
        $handle = fopen($filePath, 'r');
        if (!$handle || !stream_filter_append($handle, 'convert.iconv.UTF-16LE/UTF-8')) {
            throw new \RuntimeException("Impossible d'ouvrir/décoder le fichier : $filePath");
        }

        $header = fgetcsv($handle, 0, "\t");
        if (!$header) {
            throw new \RuntimeException('Fichier vide ou en-tête illisible : ' . $filePath);
        }
        $header[0] = preg_replace('/^\x{FEFF}/u', '', $header[0]);
        $col = array_flip($header);

        $dateIdx = $col['Date'] ?? null;
        $heureIdx = $col['Heure'] ?? null;
        $tempIdx = $col['Ch1.Température(C)'] ?? $col['Ch0.Température(C)'] ?? null;
        $humidityIdx = $col['Ch1.Humidité(%)'] ?? $col['Ch0.Humidité(%)'] ?? null;
        $windDegIdx = $col['Direction du vent(deg)'] ?? null;
        $windSpeedIdx = $col['Vitesse en rafale(km/h)'] ?? $col['Vitesse moyenne du vent(km/h)'] ?? null;
        $pressureIdx = $col['Pression (hPa)'] ?? $col['Pression (mb)'] ?? null;
        $rainRateIdx = $col['Pluviométrie(mm/h)'] ?? $col['Pluviométrie (mm/h)'] ?? null;
        $rainHourlyIdx = $col['Chute de pluie par heure (mm)'] ?? null;
        $rainAccIdx = $col['Chute de pluie accumulée (mm)'] ?? null;

        if ($dateIdx === null || $heureIdx === null || $tempIdx === null) {
            fclose($handle);
            throw new \RuntimeException("Colonnes attendues introuvables dans l'en-tête (Date/Heure/Température) : $filePath");
        }

        $paris = new \DateTimeZone('Europe/Paris');

        while (($data = fgetcsv($handle, 0, "\t")) !== false) {
            if (!isset($data[$dateIdx], $data[$heureIdx]) || $data[$dateIdx] === '') {
                continue;
            }

            $temp = CRangeValueParser::parseValue($data[$tempIdx] ?? null);
            if ($temp === null) {
                continue;
            }

            $date = \DateTime::createFromFormat('Y-m-d H:i', trim($data[$dateIdx]) . ' ' . trim($data[$heureIdx]), $paris);
            if (!$date) {
                continue;
            }

            yield [
                'dt' => $date->getTimestamp(),
                'temp' => $temp,
                'humidity' => $humidityIdx !== null ? CRangeValueParser::parseValue($data[$humidityIdx] ?? null) : null,
                'pressure' => $pressureIdx !== null ? CRangeValueParser::parseValue($data[$pressureIdx] ?? null) : null,
                'wind_speed' => $windSpeedIdx !== null ? CRangeValueParser::parseValue($data[$windSpeedIdx] ?? null) : null,
                'wind_deg' => $windDegIdx !== null ? CRangeValueParser::parseValue($data[$windDegIdx] ?? null) : null,
                'rain_rate' => $rainRateIdx !== null ? CRangeValueParser::parseValue($data[$rainRateIdx] ?? null) : null,
                'rain_hourly' => $rainHourlyIdx !== null ? CRangeValueParser::parseValue($data[$rainHourlyIdx] ?? null) : null,
                'rain_accumulated' => $rainAccIdx !== null ? CRangeValueParser::parseValue($data[$rainAccIdx] ?? null) : null,
            ];
        }
        fclose($handle);
    }

    private function readSpreadsheet(string $filePath): \Generator
    {
        $reader = IOFactory::createReaderForFile($filePath);
        $reader->setReadDataOnly(true);
        if (method_exists($reader, 'setReadEmptyCells')) {
            $reader->setReadEmptyCells(false);
        }
        $spreadsheet = $reader->load($filePath);
        $sheet = $spreadsheet->getSheet(0);

        $highestRow = $sheet->getHighestDataRow();
        $highestCol = $sheet->getHighestDataColumn();

        $header = $sheet->rangeToArray('A1:' . $highestCol . '1', null, true, false)[0];
        $col = array_flip(array_map('trim', $header));

        $dateIdx = $col['Date'] ?? null;
        $heureIdx = $col['Heure'] ?? null;
        $tempIdx = $col['Ch1.Température(C)'] ?? $col['Ch0.Température(C)'] ?? null;
        $humidityIdx = $col['Ch1.Humidité(%)'] ?? $col['Ch0.Humidité(%)'] ?? null;
        $windDegIdx = $col['Direction du vent(deg)'] ?? null;
        $windSpeedIdx = $col['Vitesse en rafale(km/h)'] ?? $col['Vitesse moyenne du vent(km/h)'] ?? null;
        $pressureIdx = $col['Pression (hPa)'] ?? $col['Pression (mb)'] ?? null;
        $rainRateIdx = $col['Pluviométrie(mm/h)'] ?? $col['Pluviométrie (mm/h)'] ?? null;
        $rainHourlyIdx = $col['Chute de pluie par heure (mm)'] ?? null;
        $rainAccIdx = $col['Chute de pluie accumulée (mm)'] ?? null;

        if ($dateIdx === null || $heureIdx === null || $tempIdx === null) {
            throw new \RuntimeException("Colonnes attendues introuvables dans l'en-tête (Date/Heure/Température) : $filePath");
        }

        $paris = new \DateTimeZone('Europe/Paris');

        $rows = $highestRow >= 2
            ? $sheet->rangeToArray('A2:' . $highestCol . $highestRow, null, true, false)
            : [];

        foreach ($rows as $row) {
            $dateSerial = $row[$dateIdx] ?? null;
            $timeSerial = $row[$heureIdx] ?? null;
            if (!is_numeric($dateSerial) || !is_numeric($timeSerial)) {
                continue;
            }

            $temp = CRangeValueParser::parseValue($row[$tempIdx] ?? null);
            if ($temp === null) {
                continue;
            }

            $date = CRangeValueParser::excelDateTimeToParis((float) $dateSerial, (float) $timeSerial, $paris);
            if (!$date) {
                continue;
            }

            yield [
                'dt' => $date->getTimestamp(),
                'temp' => $temp,
                'humidity' => $humidityIdx !== null ? CRangeValueParser::parseValue($row[$humidityIdx] ?? null) : null,
                'pressure' => $pressureIdx !== null ? CRangeValueParser::parseValue($row[$pressureIdx] ?? null) : null,
                'wind_speed' => $windSpeedIdx !== null ? CRangeValueParser::parseValue($row[$windSpeedIdx] ?? null) : null,
                'wind_deg' => $windDegIdx !== null ? CRangeValueParser::parseValue($row[$windDegIdx] ?? null) : null,
                'rain_rate' => $rainRateIdx !== null ? CRangeValueParser::parseValue($row[$rainRateIdx] ?? null) : null,
                'rain_hourly' => $rainHourlyIdx !== null ? CRangeValueParser::parseValue($row[$rainHourlyIdx] ?? null) : null,
                'rain_accumulated' => $rainAccIdx !== null ? CRangeValueParser::parseValue($row[$rainAccIdx] ?? null) : null,
            ];
        }

        $spreadsheet->disconnectWorksheets();
    }
}
