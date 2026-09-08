<?php

namespace App\Service\CRange;

use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Aide au parsing des exports station CRange (Orégon WMR300), qu'ils soient au
 * format texte UTF-16LE tabulé (export "brut" de la station) ou XLSX/XLS réel
 * (fichiers ré-enregistrés depuis Excel/LibreOffice).
 */
class CRangeValueParser
{
    /** Convertit une cellule en float, ou null si vide/absente/"--"/"NA"/"?". */
    public static function parseValue(mixed $raw): ?float
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $raw = trim(str_replace(',', '.', (string) $raw));
        if ($raw === '' || $raw === '--' || $raw === '?' || strcasecmp($raw, 'NA') === 0) {
            return null;
        }
        return is_numeric($raw) ? (float) $raw : null;
    }

    /**
     * Combine une date et une heure sérialisées Excel (colonnes séparées Date +
     * Heure des exports CRange) en DateTime Europe/Paris, au format identique à
     * ce que produit le parsing du texte tabulé ("Y-m-d H:i" -> Paris).
     */
    public static function excelDateTimeToParis(float $dateSerial, float $timeSerial, \DateTimeZone $paris): ?\DateTime
    {
        $combined = ExcelDate::excelToDateTimeObject($dateSerial + $timeSerial);

        return \DateTime::createFromFormat('Y-m-d H:i', $combined->format('Y-m-d H:i'), $paris) ?: null;
    }
}
