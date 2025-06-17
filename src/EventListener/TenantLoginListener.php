<?php

namespace App\EventListener;

use App\Entity\Main\User;
use App\Service\TenantDatabaseManager;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: 'security.interactive_login', method: 'onInteractiveLogin')]
class TenantLoginListener
{
    public function __construct(
        private TenantDatabaseManager $tenantDatabaseManager
    ) {}

    public function onInteractiveLogin(InteractiveLoginEvent $event): void
    {
        $user = $event->getAuthenticationToken()->getUser();

        if ($user instanceof User) {
            $this->tenantDatabaseManager->switchTenantConnection($user);
        }
    }
}