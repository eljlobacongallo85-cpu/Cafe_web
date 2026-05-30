<?php

namespace App\EventListener;

use App\Entity\User;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use App\Service\ActivityLogger;

class LoginListener
{
    public function __construct(private ActivityLogger $logger) {}

    public function onSecurityInteractiveLogin(InteractiveLoginEvent $event): void
    {
        $user = $event->getAuthenticationToken()->getUser();
        if (!$user instanceof User) {
            return;
        }

        $this->logger->log(
            $user,
            'LOGIN',
            $user->getUserIdentifier() . ' logged in'
        );
    }
}
