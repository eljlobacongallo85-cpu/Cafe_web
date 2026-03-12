<?php

namespace App\Controller;

use App\Entity\ActivityLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class ActivityLogger
{
    
    private $em;
    private $security;

    public function __construct(EntityManagerInterface $em, Security $security)
    {
        $this->em = $em;
        $this->security = $security;
    }

    public function record(string $action, string $target)
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return; // Prevent null errors
        }

        $log = new ActivityLog();
        $log->setUserId($user->getId());
        $log->setUsername($user->getUserIdentifier());
        $log->setRole($user->getRoles()[0]);
        $log->setAction($action);
        $log->setTargetData($target);
        $log->setCreatedAt(new \DateTimeImmutable());

        $this->em->persist($log);
        $this->em->flush();
    }
}
