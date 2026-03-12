<?php

namespace App\Controller;

use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminDashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'admin_dashboard')]
    public function index(
        OrderRepository $orderRepository,
        ProductRepository $productRepository
    ): Response {
        $orders = $orderRepository->findAll();

        $totalSales = array_sum(
            array_map(fn ($o) => $o->getTotalPrice(), $orders)
        );

        $totalOrders = count($orders);
        $totalProducts = $productRepository->count([]);
        $totalDonation = $totalSales * 0.10;

        return $this->render('admin/dashboard/index.html.twig', [
            'totalSales'    => $totalSales,
            'totalOrders'   => $totalOrders,
            'totalProducts' => $totalProducts,
            'totalDonation' => $totalDonation,
            'orders'        => $orders, // REQUIRED for Recent Orders
        ]);
    }
}
