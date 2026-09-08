<?php

namespace App\Command;

use App\Service\CRange\CRangeSyncService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Synchro incrémentale CRange depuis un mirroir local du dossier Drive "Orégon
 * sauve graph/" — utile pour un test manuel. Le déclenchement normal passe par
 * le bouton "Maj CRange" du site (ROLE_CRANGE), voir CRangeController::sync,
 * qui télécharge lui-même les fichiers depuis Drive (CRangeDriveClient) avant
 * d'appeler le même CRangeSyncService::syncDirectory().
 */
class CRangeSyncCommand extends Command
{
    protected static $defaultName = 'app:crange:sync';

    public function __construct(private CRangeSyncService $syncService)
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription("Synchro incrémentale CRange depuis un mirroir local du dossier Drive 'Orégon sauve graph/'.")
            ->addArgument('dir', InputArgument::REQUIRED, 'Dossier local mirroir de "Orégon sauve graph/"');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dir = rtrim($input->getArgument('dir'), '/');

        if (!is_dir($dir)) {
            $output->writeln("<error>Dossier introuvable : $dir</error>");

            return Command::FAILURE;
        }

        ini_set('memory_limit', '1G');

        $result = $this->syncService->syncDirectory($dir);

        foreach ($result['files'] as $file) {
            if ($file['status'] === 'unchanged') {
                $output->writeln("- {$file['filename']} : inchangé, ignoré");
            } else {
                $output->writeln(sprintf('- %s : %d nouvelle(s) ligne(s)', $file['filename'], $file['rowsInserted']));
            }
        }

        $output->writeln(sprintf(
            '<info>Synchro terminée : %d ligne(s) minutely ajoutée(s), %d jour(s) recalculé(s).</info>',
            $result['totalRowsInserted'],
            $result['daysRecomputed']
        ));

        return Command::SUCCESS;
    }
}
