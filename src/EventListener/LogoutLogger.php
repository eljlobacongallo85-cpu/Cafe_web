<?php

namespace App\EventListener;

use App\Entity\User;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use App\Service\ActivityLogger;

class LogoutLogger
{
    public function __construct(private ActivityLogger $logger) {}

    public function onLogout(LogoutEvent $event): void
    {
        $user = $event->getToken()?->getUser();

        if (!$user instanceof User) {
            return;
        }

        $this->logger->log(
            $user,
            'LOGOUT',
            $user->getUserIdentifier() . ' logged out'
        );
    }
}
