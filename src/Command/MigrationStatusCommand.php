<?php

namespace App\Command;

use App\Service\MigrationManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;

#[AsCommand(
    name: 'app:migration:status',
    description: 'Affiche un résumé des migrations par EM avec statut global',
)]
class MigrationStatusCommand extends Command
{
    public function __construct(
        private readonly MigrationManager $migrationManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('em', null, InputOption::VALUE_OPTIONAL, 'EntityManager à cibler (main, tenant, etc.)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $targetEms = $input->getOption('em')
            ? [$input->getOption('em')]
            : $this->migrationManager->getAvailableEntityManagers();

        $table = new Table($output);
        $table->setHeaders(['Entity Manager', 'Executed', 'New', 'Available', 'Pending', 'Status']);

        foreach ($targetEms as $em) {
            try {
                $status = $this->migrationManager->getMigrationStatus($em);

                $executed = count($status['executed']);
                $new = count($status['new']);
                $available = count($status['available']);
                $pending = count($status['pending']);

                $global = $pending > 0 ? 'Pending' : 'Up-to-date';

                $table->addRow([$em, $executed, $new, $available, $pending, $global]);
            } catch (\Throwable $e) {
                $table->addRow([$em, 'ERR', 'ERR', 'ERR', 'ERR', $e->getMessage()]);
            }
        }

        $table->render();
        return Command::SUCCESS;
    }
}
