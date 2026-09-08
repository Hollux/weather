<?php

namespace App\Command;

use App\Service\CRange\CRangeSunshineImportService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Comble les jours sans aucune ligne weather_daily_crange (années sans export
 * minutely dans l'archive Drive, ex: 2019) à partir des classeurs "Météo
 * <année> Orégon.xls" assemblés à la main — voir
 * CRangeSunshineImportService::importMissingDailySummary(). Résultat
 * approximatif (pas de vraies moyennes, seulement des maxi/mini) : ne
 * remplace jamais un jour déjà présent (INSERT IGNORE).
 */
class CRangeImportDailySummaryCommand extends Command
{
    protected static $defaultName = 'app:crange:import-daily-summary';

    public function __construct(private CRangeSunshineImportService $sunshineImportService)
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription("Comble les jours daily manquants à partir d'un classeur 'Météo <année> Orégon.xls'.")
            ->addArgument('file', InputArgument::REQUIRED, 'Chemin du fichier "Météo <année> Orégon.xls(x)"');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $file = $input->getArgument('file');

        if (!is_file($file)) {
            $output->writeln("<error>Fichier introuvable : $file</error>");

            return Command::FAILURE;
        }

        ini_set('memory_limit', '1G');

        $result = $this->sunshineImportService->importMissingDailySummary($file);

        $output->writeln(sprintf(
            '<info>%d jour(s) créé(s), %d déjà existants (ignorés), %d dates illisibles.</info>',
            $result['inserted'],
            $result['skippedExisting'],
            $result['skippedUnreadable']
        ));

        return Command::SUCCESS;
    }
}
