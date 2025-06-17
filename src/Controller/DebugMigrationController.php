<?php

namespace App\Controller;


use App\Service\MigrationManager;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class DebugMigrationController extends AbstractController
{
    public function __construct(private readonly MigrationManager $migrationManager) {}

    #[Route('/debug/migration/{em}', name: 'debug_migration')]
    public function debugMigration(string $em = 'default'): Response
    {
        try {
            $status = $this->migrationManager->getMigrationStatus($em);
            $formatted = [
                'executed' => array_map(fn($m) => (string) $m->getVersion(), $status['executed']),
                'new' => array_map(fn($m) => (string) $m->getVersion(), $status['new']),
                'available' => array_map(fn($m) => (string) $m->getVersion(), $status['available']),
                'pending' => $status['pending'],
            ];

            return $this->json([
                'entityManager' => $em,
                'status' => $formatted,
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/debug/df/{em}', name: 'debug_df')]
    public function debugDependencyFactory(string $em = 'main'): Response
    {
        try {
            $df = $this->migrationManager->getDependencyFactory($em);
            return $this->json([
                'success' => true,
                'df' => $df,
            ], 200);
        } catch (\Throwable $e) {
            return $this->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
