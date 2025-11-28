<?php
require 'vendor/autoload.php';

$csvFile = __DIR__ . '/weather_hwminutely.csv';
$outputDir = __DIR__ . '/src/DataFixtures/backup_fixtures/';

if (!is_dir($outputDir)) {
    mkdir($outputDir, 0777, true);
}

$handleCsv = fopen($csvFile, 'r');
if (!$handleCsv) die("Impossible d'ouvrir le CSV\n");

$chunkSize = 50000;
$chunk = [];
$fileIndex = 1;

while (($line = fgetcsv($handleCsv)) !== false) {
    $chunk[] = $line;

    if (count($chunk) === $chunkSize) {
        writeFixture($chunk, $fileIndex++, $outputDir);
        $chunk = [];
    }
}

// Dernier morceau
if (count($chunk) > 0) {
    writeFixture($chunk, $fileIndex++, $outputDir);
}

fclose($handleCsv);

echo "Toutes les fixtures générées avec succès dans $outputDir\n";

function writeFixture(array $chunk, int $index, string $outputDir)
{
    $className = "WeatherHwMinutelyBackupFixturesPart{$index}";
    $outputFile = $outputDir . "{$className}.php";
    $handleOut = fopen($outputFile, 'w');
    if (!$handleOut) die("Impossible de créer le fichier $outputFile\n");

    fwrite($handleOut, "<?php\n\nnamespace App\\DataFixtures\\backup_fixtures;\n\nuse Doctrine\\Bundle\\FixturesBundle\\Fixture;\nuse Doctrine\\Persistence\\ObjectManager;\nuse App\\Entity\\WeatherHWminutely;\n\nclass $className extends Fixture\n{\n    public function load(ObjectManager \$manager)\n    {\n        \$data = [\n");

    foreach ($chunk as $line) {
        [$id, $dt, $temp, $pressure, $humidity, $uvi, $wind_speed, $wind_deg, $wind_speed_kmh] = $line;
        fwrite($handleOut, "            [{$dt}, '{$temp}', '{$pressure}', '{$humidity}', '{$uvi}', '{$wind_speed}', '{$wind_deg}', '{$wind_speed_kmh}'],\n");
    }

    fwrite($handleOut, <<<PHP
        ];

        foreach (\$data as [\$dt, \$temp, \$pressure, \$humidity, \$uvi, \$wind_speed, \$wind_deg, \$wind_speed_kmh]) {
            \$entry = new WeatherHWminutely();
            \$entry->setDt((int)\$dt);
            \$entry->setTemp(\$temp);
            \$entry->setPressure(\$pressure);
            \$entry->setHumidity(\$humidity);
            \$entry->setUvi(\$uvi);
            \$entry->setWindSpeed(\$wind_speed);
            \$entry->setWindDeg(\$wind_deg);
            \$entry->setWindSpeedKmh(\$wind_speed_kmh);
            \$manager->persist(\$entry);

            static \$i = 0;
            if (++\$i % 1000 === 0) {
                \$manager->flush();
                \$manager->clear();
            }
        }

        \$manager->flush();
        \$manager->clear();
    }
}
PHP
    );

    fclose($handleOut);
    echo "Fixture générée : $outputFile\n";
}
