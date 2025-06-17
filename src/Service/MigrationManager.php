<?php

namespace App\Service;

use Doctrine\Migrations\Configuration\Connection\ExistingConnection;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Configuration\Migration\ExistingConfiguration;
use Doctrine\Migrations\Configuration\Migration\PhpFile;
use Doctrine\Migrations\Metadata\Storage\MetadataStorage;
use Symfony\Component\Filesystem\Filesystem;

class MigrationManager
{
    public function __construct(
        private readonly ManagerRegistry $registry,
        private readonly Filesystem $filesystem
    ) {}

    public function getDependencyFactory(string $em, ?string $configPath = null): DependencyFactory
    {
        $connection = $this->registry->getConnection($em);
        $configPath ??= $this->resolveConfigPath($em);
        $config = (new PhpFile($configPath))->getConfiguration();

        return DependencyFactory::fromConnection(new ExistingConfiguration($config), new ExistingConnection($connection));
    }

    public function getMetadataStorage(string $em): MetadataStorage
    {
        return $this->getDependencyFactory($em)
            ->getMetadataStorage();
    }

    public function getMigrationStatus(string $em): array
    {
        $df = $this->getDependencyFactory($em);
        $planCalculator = $df->getMigrationPlanCalculator();
        $statusCalculator = $df->getMigrationStatusCalculator();

        $availableMigrations = $df->getMigrationRepository()->getMigrations();
        $latestMigration = end($availableMigrations);
        $latestVersion = $latestMigration[0]->getVersion();

        $pending = [];
        if ($latestVersion !== null) {
            $pendingPlan = $planCalculator->getPlanUntilVersion($latestVersion);

            foreach ($pendingPlan->getItems() as $planItem) {
                $pending[] = (string) $planItem->getVersion();
            }
        }

        return [
            'executed' => $df->getMetadataStorage()->getExecutedMigrations()->getItems(),
            'new' => $statusCalculator->getNewMigrations()->getItems(),
            'available' => $availableMigrations->getItems(),
            'pending' => $pending,
        ];
    }

    private function resolveConfigPath(string $em): string
    {
        return match ($em) {
            'main', 'default' => dirname(__DIR__, 2) . '/migrations/main/.migrations.php',
            'tenant' => dirname(__DIR__, 2) . '/migrations/tenant/.migrations.php',
            default => throw new \InvalidArgumentException("Aucune config migration trouvée pour em '$em'")
        };
    }

    public function getAvailableEntityManagers(): array
    {
        return ['default', 'tenant'];
    }
}
