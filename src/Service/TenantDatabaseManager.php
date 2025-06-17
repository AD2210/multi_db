<?php

namespace App\Service;

use App\Entity\Main\User;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use App\DBAL\MultiDbConnectionWrapper;

class TenantDatabaseManager
{
    private string $tenantDbName = '';

    public function __construct(
        private ManagerRegistry $doctrine,
        private EntityManagerInterface $mainEntityManager,
        private EntityManagerInterface $tenantEntityManager
    ) {}

    public function initializeTenantDatabase(User $user): void
    {
        $this->tenantDbName = 'tenant_' . $user->getId();

        $this->createDatabase();
        $this->migrateDatabase($user);
    }

    public function switchTenantConnection(User $user): void
    {
        $this->tenantDbName = 'tenant_' . $user->getId();

        /** @var MultiDbConnectionWrapper $original */
        $original = $this->doctrine->getConnection('tenant');
        $original->switchDatabase($this->tenantDbName);

        // Remplacer manuellement la connexion dans le manager si nécessaire
        $this->doctrine->resetManager('tenant');
    }

    public function getTenantEntityManager(User $user): EntityManagerInterface
    {
        $this->tenantDbName = 'tenant_' . $user->getId();

        /** @var MultiDbConnectionWrapper $original */
        $original = $this->doctrine->getConnection('tenant');
        $connection = $original->switchDatabase($this->tenantDbName);

        $config = $this->tenantEntityManager->getConfiguration();

        return new \Doctrine\ORM\EntityManager($connection, $config);
    }

    private function createDatabase(): void
    {
        $connection = $this->mainEntityManager->getConnection();
        $schemaManager = $connection->createSchemaManager();

        if (!in_array($this->tenantDbName, $schemaManager->listDatabases())) {
            $schemaManager->createDatabase($this->tenantDbName);
        }
    }

    private function migrateDatabase(User $user): void
    {
        $entityManager = $this->getTenantEntityManager($user);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();

        if (!empty($metadata)) {
            $schemaTool = new SchemaTool($entityManager);
            $schemaTool->createSchema($metadata);
        }
    }
}
