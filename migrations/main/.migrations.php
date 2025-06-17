<?php

return [
    'table_storage' => [
        'table_name' => 'doctrine_migration_versions',
    ],
    'migrations_paths' => [
        'DoctrineMigrations\\Main' => __DIR__,
    ],
    'all_or_nothing' => true,
    'transactional' => true,
];
