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

        $products = $productRepository->findBy(['createdBy' => $user]);
        $orders   = $orderRepository->findBy([], ['createdAt' => 'DESC'], 10);

        return $this->render('staff/dashboard/index.html.twig', [
            'user'          => $user,
            'totalProducts' => count($products),
            'totalOrders'   => $orderRepository->count([]),
            'products'      => $products,
            'orders'        => $orders,
        ]);
    }
}
