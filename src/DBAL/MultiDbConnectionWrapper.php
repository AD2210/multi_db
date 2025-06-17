<?php

namespace App\DBAL;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Configuration;
use Doctrine\Common\EventManager;

class MultiDbConnectionWrapper extends Connection
{
    public function __construct(array $params, Driver $driver, Configuration $config, EventManager $eventManager)
    {
        parent::__construct($params, $driver, $config, $eventManager);
    }

    public function switchDatabase(string $dbName): self
    {
        if ($this->isConnected()) {
            $this->close();
        }

        // Impossible de modifier en profondeur la connexion actuelle.
        // Solution : reconstruire la connexion sur la base du nouveau dbname.
        $params = $this->getParams();
        $params['dbname'] = $dbName;
        dump($params);

        return new self($params, $this->_driver, $this->_config, $this->_eventManager);
    }

    public function getCurrentDatabaseName(): string
    {
        return $this->getParams()['dbname'] ?? 'undefined';
    }
}
