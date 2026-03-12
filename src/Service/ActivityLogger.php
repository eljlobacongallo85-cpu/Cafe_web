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
        private Security $security
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
        $log->setCreatedAt(new \DateTimeImmutable());

        $this->em->persist($log);
        $this->em->flush();
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
        $log->setCreatedAt(new \DateTimeImmutable());

        $this->em->persist($log);
        $this->em->flush();
    }
}
