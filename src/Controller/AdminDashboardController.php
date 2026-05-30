<?php

namespace App\Controller;

use App\Repository\OrderItemRepository;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
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
        ProductRepository $productRepository,
        OrderItemRepository $orderItemRepository
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
            'topProducts'   => $orderItemRepository->findTopSellingProducts(5),
        ]);
    }

    #[Route('/dashboard/feed', name: 'admin_dashboard_feed', methods: ['GET'])]
    public function feed(
        OrderRepository $orderRepository,
        ProductRepository $productRepository,
        OrderItemRepository $orderItemRepository
    ): JsonResponse {
        $orders = $orderRepository->findBy([], ['createdAt' => 'DESC']);
        $totalSales = array_sum(array_map(static fn ($order) => $order->getTotalPrice(), $orders));
        $totalOrders = count($orders);
        $totalDonation = $totalSales * 0.10;

        return new JsonResponse([
            'ok' => true,
            'stats' => [
                'totalSales' => (float) $totalSales,
                'totalOrders' => $totalOrders,
                'totalProducts' => $productRepository->count([]),
                'totalDonation' => (float) $totalDonation,
            ],
            'orders' => array_map(static fn ($order): array => [
                'id' => $order->getId(),
                'customerName' => $order->getCustomerName(),
                'totalPrice' => (float) $order->getTotalPrice(),
                'paymentStatus' => $order->getPaymentStatus(),
                'createdAt' => $order->getCreatedAt()?->format(DATE_ATOM),
            ], array_slice($orders, 0, 8)),
            'topProducts' => array_map(static fn (array $product): array => [
                'name' => $product['name'] ?? '',
                'sold' => (int) ($product['sold'] ?? 0),
            ], $orderItemRepository->findTopSellingProducts(5)),
        ]);
    }
}
