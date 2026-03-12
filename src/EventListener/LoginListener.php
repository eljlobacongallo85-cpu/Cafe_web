<?php

namespace App\EventListener;

use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use App\Service\ActivityLogger;

class LoginListener
{
    public function __construct(private ActivityLogger $logger) {}

    public function onSecurityInteractiveLogin(InteractiveLoginEvent $event): void
    {
        $user = $event->getAuthenticationToken()->getUser();

        $this->logger->log(
            $user,
            'Login',
            $user->getUserIdentifier() . ' logged in'
        );
    }
}
