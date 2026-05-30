<?php

namespace App\Controller;

use App\Entity\ActivityLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ActivityLogController extends AbstractController
{
    #[Route('/admin/activity', name: 'admin_activity')]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $username = $request->query->get('username');
        $action = $request->query->get('action');
        $date = $request->query->get('date');

        $qb = $em->getRepository(ActivityLog::class)->createQueryBuilder('l')
            ->orderBy('l.createdAt', 'DESC');

        if ($username) {
            $qb->andWhere('l.username LIKE :user')
               ->setParameter('user', "%$username%");
        }

        if ($action && $action !== 'ALL') {
            $qb->andWhere('l.action = :action')
               ->setParameter('action', $action);
        }

        if ($date) {
            $dateStart = new \DateTime($date . " 00:00:00");
            $dateEnd   = new \DateTime($date . " 23:59:59");

            $qb->andWhere('l.createdAt BETWEEN :start AND :end')
               ->setParameter('start', $dateStart)
               ->setParameter('end', $dateEnd);
        }

        $logs = $qb->getQuery()->getResult();

        return $this->render('admin/activity/index.html.twig', [
            'logs' => $logs,
            'filters' => [
                'username' => $username,
                'action' => $action,
                'date' => $date,
            ]
        ]);
    }

    #[Route('/admin/activity/feed', name: 'admin_activity_feed', methods: ['GET'])]
    public function feed(EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $logs = $em->getRepository(ActivityLog::class)
            ->createQueryBuilder('l')
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults(100)
            ->getQuery()
            ->getResult();

        return new JsonResponse([
            'ok' => true,
            'logs' => array_map(static fn (ActivityLog $log): array => [
                'id' => $log->getId(),
                'username' => $log->getUsername(),
                'role' => $log->getRole(),
                'action' => $log->getAction(),
                'targetData' => $log->getTargetData(),
                'createdAt' => $log->getCreatedAt()?->format(DATE_ATOM),
            ], $logs),
        ]);
    }
}
