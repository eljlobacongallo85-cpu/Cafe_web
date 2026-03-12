<?php

namespace App\EventListener;

use Symfony\Component\Security\Http\Event\LogoutEvent;
use App\Service\ActivityLogger;

class LogoutLogger
{
    public function __construct(private ActivityLogger $logger) {}

    public function onLogout(LogoutEvent $event): void
    {
        $user = $event->getToken()?->getUser();

        if ($user) {
            $this->logger->log(
                $user,
                'Logout',
                $user->getUserIdentifier() . ' logged out'
            );
        }
    }
}
