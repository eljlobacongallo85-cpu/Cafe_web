<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class WorkerDashboardController extends AbstractController
{
    #[Route('/worker/dashboard', name: 'worker_dashboard')]
    public function index(): Response
    {
        $assignedOrders = [
            ['id' => 101, 'item' => 'Cappuccino', 'status' => 'Preparing'],
            ['id' => 102, 'item' => 'Iced Mocha', 'status' => 'Ready'],
            ['id' => 103, 'item' => 'Espresso', 'status' => 'Pending'],
        ];

        $shift = "Morning Shift (8:00 AM - 4:00 PM)";

        return $this->render('worker_dashboard/index.html.twig', [
            'assignedOrders' => $assignedOrders,
            'shift' => $shift,
        ]);
    }
}
