<?php

namespace App\Command;

use App\Service\CRange\CRangeSunshineImportService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Backfill de weather_daily_crange.sunshine_minutes à partir des classeurs
 * "Météo <année> Orégon.xls" assemblés à la main (voir CRangeSunshineImportService).
 * Dossier local typiquement rempli via rclone côté hôte (scripts/crange-sync/).
 */
class CRangeImportSunshineCommand extends Command
{
    protected static $defaultName = 'app:crange:import-sunshine';

    public function __construct(private CRangeSunshineImportService $sunshineImportService)
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription("Backfill de sunshine_minutes à partir des classeurs 'Météo <année> Orégon.xls'.")
            ->addArgument('dir', InputArgument::REQUIRED, 'Dossier local contenant les fichiers "Météo <année> Orégon.xls(x)"');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dir = rtrim($input->getArgument('dir'), '/');

        if (!is_dir($dir)) {
            $output->writeln("<error>Dossier introuvable : $dir</error>");

            return Command::FAILURE;
        }

        ini_set('memory_limit', '1G');

        $files = glob($dir . '/*.xls*');
        sort($files);

        if (empty($files)) {
            $output->writeln("<comment>Aucun fichier .xls/.xlsx dans $dir</comment>");

            return Command::SUCCESS;
        }

        $totalUpdated = 0;
        $totalSkippedNoRow = 0;
        $totalSkippedUnreadable = 0;
        foreach ($files as $file) {
            $output->write('- ' . basename($file) . ' ... ');
            $result = $this->sunshineImportService->importFile($file);
            $output->writeln(sprintf(
                '%d jours mis à jour, %d sans ligne daily existante, %d dates illisibles',
                $result['updated'],
                $result['skippedNoRow'],
                $result['skippedUnreadable']
            ));
            $totalUpdated += $result['updated'];
            $totalSkippedNoRow += $result['skippedNoRow'];
            $totalSkippedUnreadable += $result['skippedUnreadable'];
        }

        $output->writeln(sprintf(
            '<info>Backfill ensoleillement terminé : %d fichier(s), %d jours mis à jour, %d ignorés (pas de ligne daily), %d dates illisibles.</info>',
            count($files),
            $totalUpdated,
            $totalSkippedNoRow,
            $totalSkippedUnreadable
        ));

        return Command::SUCCESS;
    }
}
