<?php

namespace App\Controller;

use App\Entity\Order;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/orders')]
#[IsGranted('ROLE_STAFF')] // staff + admin
class AdminOrderController extends AbstractController
{
    #[Route('/', name: 'admin_order_index')]
    public function index(OrderRepository $orderRepository): Response
    {
        $orders = $orderRepository->findBy([], ['createdAt' => 'DESC']);

        return $this->render('admin/orders/index.html.twig', [
            'orders' => $orders,
        ]);
    }

    #[Route('/feed', name: 'admin_order_feed', methods: ['GET'])]
    public function feed(OrderRepository $orderRepository): JsonResponse
    {
        $totalCount = $orderRepository->count([]);
        $orders = $orderRepository->findBy([], ['createdAt' => 'DESC'], 100);

        return new JsonResponse([
            'ok' => true,
            'totalCount' => $totalCount,
            'orders' => array_map(static fn (Order $order) => [
                'id' => $order->getId(),
                'customerName' => $order->getCustomerName(),
                'totalPrice' => (float) $order->getTotalPrice(),
                'createdAt' => $order->getCreatedAt()?->format(DATE_ATOM),
            ], $orders),
        ]);
    }

    #[Route('/{id}', name: 'admin_order_show')]
    public function show(Order $order): Response
    {
        return $this->render('admin/orders/show.html.twig', [
            'order' => $order,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_order_delete')]
    #[IsGranted('ROLE_ADMIN')] // make delete admin-only (optional) — change if staff should delete orders
    public function delete(Order $order, EntityManagerInterface $em): Response
    {
        $em->remove($order);
        $em->flush();

        $this->addFlash('danger', '🗑 Order deleted successfully.');

        return $this->redirectToRoute('admin_order_index');
    }
}
