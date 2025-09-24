<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class OwnerDashboardController extends AbstractController
{
    #[Route('/owner/dashboard', name: 'owner_dashboard')]
    public function index(): Response
    {
        $salesToday = 5000; // pesos
        $topProduct = "Caramel Latte";
        $donationRate = 0.10; // 10%
        $donationAmount = $salesToday * $donationRate;

        return $this->render('owner_dashboard/index.html.twig', [
            'salesToday' => $salesToday,
            'topProduct' => $topProduct,
            'donationAmount' => $donationAmount,
        ]);
    }
}
