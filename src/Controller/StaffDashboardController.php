<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use App\Repository\OrderRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/staff')]
#[IsGranted('ROLE_STAFF')]
class StaffDashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'staff_dashboard')]
    public function index(
        ProductRepository $productRepository,
        OrderRepository $orderRepository
    ): Response {
        $user = $this->getUser();

        // Staff can only see THEIR OWN records
        $products = $productRepository->findBy(['createdBy' => $user]);
        $orders   = $orderRepository->findBy(['createdBy' => $user]);

        return $this->render('staff/dashboard/index.html.twig', [
            'user'          => $user,
            'totalProducts' => count($products),
            'totalOrders'   => count($orders),
            'products'      => $products,
            'orders'        => $orders,
        ]);
    }
}
