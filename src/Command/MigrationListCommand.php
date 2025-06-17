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
    name: 'app:migration:list',
    description: 'Liste les migrations par EM (exécutées, nouvelles, en attente, disponibles)',
)]
class MigrationListCommand extends Command
{
    public function __construct(
        private readonly MigrationManager $migrationManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('em', null, InputOption::VALUE_OPTIONAL, 'EntityManager à cibler (main, tenant, etc.)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $targetEms = $input->getOption('em')
            ? [$input->getOption('em')]
            : $this->migrationManager->getAvailableEntityManagers();

        foreach ($targetEms as $em) {
            $output->writeln("\n<info>Entity Manager : $em</info>");
            try {
                $status = $this->migrationManager->getMigrationStatus($em);

                $table = new Table($output);
                $table->setHeaders(['Type', 'Migrations']);

                foreach ([
                    '<comment>Executed</comment>' => $status['executed'],
                    'New' => $status['new'],
                    '<comment>Available</comment>' => $status['available'],
                    'Pending' => $status['pending'],
                ] as $label => $versions) {
                    $versions = is_array($versions) ? $versions : iterator_to_array($versions);
                    $formatted = array_map(
                        fn($v) => method_exists($v, 'getVersion') ? (string) $v->getVersion() : (string) $v,
                        $versions
                    );
                    $table->addRow([$label, implode("\n", $formatted)]);
                }

                $table->render();
            } catch (\Throwable $e) {
                $output->writeln("<error>Erreur pour '$em' : {$e->getMessage()}</error>");
            }
        }

        return Command::SUCCESS;
    }
}
