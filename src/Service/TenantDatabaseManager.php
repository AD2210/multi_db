<?php

namespace App\Service;

use App\Entity\Main\User;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\Configuration;
use Doctrine\DBAL\DriverManager;
use Doctrine\Common\EventManager;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

class TenantDatabaseManager
{
    private string $tenantDbName = '';
    
    public function __construct(
        private ManagerRegistry $doctrine,
        private EntityManagerInterface $mainEntityManager
    ) {}

    public function initializeTenantDatabase(User $user): void
    {
        $this->tenantDbName = 'tenant_' . $user->getId();

        $this->createDatabase();

        $this->migrateDatabase();
    }
    public function switchTenantConnection(User $user): void
    {
        // Fermer la connexion existante du tenant si ouverte
        $this->tenantDbName = 'tenant_' . $user->getId();
        $tenantConnection = $this->getTenantConnection();
        if ($tenantConnection->isConnected()) {
            $tenantConnection->close();
        }

        // Remplacer la connexion "tenant" existante dans le container
        $this->doctrine->getConnection('tenant')->close();
        $this->doctrine->getManager('tenant')->clear();

        $this->doctrine->resetManager('tenant');
    }

    private function createDatabase(): void
    {
        $connection = $this->mainEntityManager->getConnection();
        $schemaManager = $connection->createSchemaManager();

        if (!in_array($this->tenantDbName, $schemaManager->listDatabases())) {
            $schemaManager->createDatabase($this->tenantDbName);
        }
    }

    private function getTenantConnection(): Connection
    {
        $params = $this->mainEntityManager->getConnection()->getParams();
        $params['dbname'] = $this->tenantDbName;
        unset($params['url']);

        $config = $this->getConfig();
        $eventManager = $this->getEventManager();

        $tenantConnection = DriverManager::getConnection($params, $config, $eventManager);
        assert($tenantConnection instanceof Connection); // proposer par chat pour controler le type de la variable

        return $tenantConnection;
    }

    private function getTenantEntityManager(): EntityManagerInterface
    {
        $tenantEntityManager = $this->doctrine->getManager('tenant');
        if (!$tenantEntityManager) {
            $tenantEntityManager = $this->createEntityManager();
        }

        return $tenantEntityManager;
    }

    private function createEntityManager(): EntityManagerInterface
    {
        $tenantConnection = $this->getTenantConnection();
        $config = $this->getConfig();
        $eventManager = $this->getEventManager();
        $tenantEntityManager = new \Doctrine\ORM\EntityManager($tenantConnection, $config, $eventManager);

        return $tenantEntityManager;
    }

    private function migrateDatabase(): void
    {
        if (!empty($this->getTenantMetadata())) {
            $schemaTool = new SchemaTool($this->getTenantEntityManager());
            $schemaTool->createSchema($this->getTenantMetadata());
        }
    }

    private function getTenantMetadata(): array
    {
        return $this->getTenantEntityManager()->getMetadataFactory()->getAllMetadata();
    }

    private function getConfig(): Configuration
    {
        return $this->mainEntityManager->getConfiguration();
    }

    private function getEventManager(): EventManager
    {
        return $this->mainEntityManager->getEventManager();
    }
}
