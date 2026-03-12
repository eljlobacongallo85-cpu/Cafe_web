<?php

namespace App\Controller;

use App\Repository\OrderRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/reports')]
#[IsGranted('ROLE_ADMIN')] // admin-only
class AdminReportsController extends AbstractController
{
    #[Route('/', name: 'admin_reports')]
    public function index(OrderRepository $orderRepository): Response
    {
        $orders = $orderRepository->findAll();

        $totalSales = 0;
        $totalOrders = count($orders);
        $salesByMonth = [];

        foreach ($orders as $order) {
            $totalSales += $order->getTotalPrice();

            $month = $order->getCreatedAt()->format('F Y');
            if (!isset($salesByMonth[$month])) {
                $salesByMonth[$month] = 0;
            }
            $salesByMonth[$month] += $order->getTotalPrice();
        }

        ksort($salesByMonth);

        $salesLabels = array_keys($salesByMonth);
        $salesValues = array_values($salesByMonth);

        $donation = $totalSales * 0.10;

        return $this->render('admin/reports/index.html.twig', [
            'totalSales'  => $totalSales,
            'totalOrders' => $totalOrders,
            'donation'    => $donation,
            'salesLabels' => $salesLabels,
            'salesValues' => $salesValues,
        ]);
    }
}
