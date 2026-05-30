<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\ActivityLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class ActivityLogger
{
    public function __construct(
        private EntityManagerInterface $em,
        private Security $security,
        private OrderRealtimePublisher $realtimePublisher
    ) {}

    /**
     * NEW: Accepts full log details (User, action, details)
     */
    public function log(User $user, string $action, string $details): void
    {
        $log = new ActivityLog();
        $log->setUserId($user->getId());
        $log->setUsername($user->getUserIdentifier());
        $log->setRole($user->getRoles()[0] ?? 'ROLE_USER');
        $log->setAction($action);
        $log->setTargetData($details);
        $log->setDetails($details);
        $log->setCreatedAt(new \DateTimeImmutable());

        $this->em->persist($log);
        $this->em->flush();
        $this->publish($log);
    }

    /**
     * OLD simplified logger – still kept for reuse
     */
    public function record(string $action, string $target): void
    {
        $user = $this->security->getUser() ?? null;

        $log = new ActivityLog();

        if ($user instanceof User) {
            $log->setUserId($user->getId());
            $log->setUsername($user->getUserIdentifier());
            $log->setRole($user->getRoles()[0] ?? 'ROLE_USER');
        } else {
            $log->setUserId(null);
            $log->setUsername('system');
            $log->setRole('SYSTEM');
        }

        $log->setAction($action);
        $log->setTargetData($target);
        $log->setDetails($target);
        $log->setCreatedAt(new \DateTimeImmutable());

        $this->em->persist($log);
        $this->em->flush();
        $this->publish($log);
    }

    private function publish(ActivityLog $log): void
    {
        $this->realtimePublisher->publishActivityLogged([
            'id' => $log->getId(),
            'username' => $log->getUsername(),
            'role' => $log->getRole(),
            'action' => $log->getAction(),
            'targetData' => $log->getTargetData(),
            'createdAt' => $log->getCreatedAt()?->format(DATE_ATOM),
        ]);
    }
}
