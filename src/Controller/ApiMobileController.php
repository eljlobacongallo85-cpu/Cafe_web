<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use App\Service\ActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api')]
class ApiMobileController extends AbstractController
{
    #[Route('/products', name: 'api_products_list', methods: ['GET'])]
    public function listProducts(ProductRepository $products): JsonResponse
    {
        $data = array_map(
            fn ($product) => $this->serializeProduct($product),
            $products->findBy([], ['name' => 'ASC'])
        );

        return new JsonResponse([
            'ok' => true,
            'message' => 'Available products loaded.',
            'products' => $data,
        ]);
    }

    // Alias for older mobile/Postman collections
    #[Route('/menu', name: 'api_menu_list', methods: ['GET'])]
    public function listMenu(ProductRepository $products): JsonResponse
    {
        return $this->listProducts($products);
    }

    #[Route('/orders', name: 'api_orders_list', methods: ['GET'])]
    #[IsGranted('ROLE_CUSTOMER')]
    public function listOrders(OrderRepository $orders): JsonResponse
    {
        $payload = array_map(
            fn ($order) => $this->serializeOrder($order),
            $orders->findBy(['createdBy' => $this->getUser()], ['createdAt' => 'DESC'])
        );

        return new JsonResponse([
            'ok' => true,
            'message' => 'Orders retrieved.',
            'orders' => $payload,
        ]);
    }

    #[Route('/orders', name: 'api_orders_create', methods: ['POST'])]
    #[IsGranted('ROLE_CUSTOMER')]
    public function createOrder(
        Request $request,
        ProductRepository $products,
        EntityManagerInterface $em,
        ActivityLogger $logger
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            $payload = $request->request->all();
        }

        $items = $payload['items'] ?? [];
        if (!is_array($items) || $items === []) {
            return new JsonResponse([
                'ok' => false,
                'message' => 'Order must contain at least one item.',
            ], 400);
        }

        $user = $this->getUser();
        $customerName = trim((string) ($payload['customerName'] ?? ($user && method_exists($user, 'getName') ? $user->getName() : 'Guest')));
        $contact = trim((string) ($payload['contact'] ?? 'N/A'));
        $notes = trim((string) ($payload['notes'] ?? ''));
        $paymentMethod = strtolower(trim((string) (
            $payload['payment_method']
            ?? $payload['paymentMethod']
            ?? $payload['payment_provider']
            ?? 'cash'
        )));
        $allowedPaymentMethods = ['cash', 'gcash', 'card'];
        if (!in_array($paymentMethod, $allowedPaymentMethods, true)) {
            $paymentMethod = 'cash';
        }

        $order = new Order();
        $order->setCustomerName($customerName ?: 'Guest');
        $order->setContact($contact ?: 'N/A');
        $order->setNotes($notes !== '' ? $notes : null);
        $order->setCreatedBy($user);
        $order->setPaymentProvider($paymentMethod);
        $order->setPaymentSessionId(null);
        $order->setPaymentStatus(Order::PAYMENT_STATUS_PAID);
        $order->setPaidAt(new \DateTimeImmutable());

        $total = 0.0;

        foreach ($items as $item) {
            $productId = (int) ($item['productId'] ?? 0);
            $quantity = max(1, (int) ($item['quantity'] ?? 1));

            $product = $products->find($productId);
            if (!$product) {
                return new JsonResponse([
                    'ok' => false,
                    'message' => sprintf('Product %d not found.', $productId),
                ], 404);
            }

            $subtotal = $product->getPrice() * $quantity;

            $orderItem = new OrderItem();
            $orderItem->setProduct($product);
            $orderItem->setQuantity($quantity);
            $orderItem->setSubtotal($subtotal);
            $order->addItem($orderItem);

            $total += $subtotal;
        }

        $order->setTotalPrice($total);

        $em->persist($order);
        $em->flush();

        $logger->record('CREATE', sprintf('Order #%d created via API', $order->getId()));

        return new JsonResponse([
            'ok' => true,
            'message' => 'Order created successfully.',
            'order' => $this->serializeOrder($order),
        ], 201);
    }

    #[Route('/orders/{id}', name: 'api_orders_show', methods: ['GET'])]
    #[IsGranted('ROLE_CUSTOMER')]
    public function showOrder(int $id, OrderRepository $orders): JsonResponse
    {
        $order = $orders->find($id);
        if (!$order) {
            return new JsonResponse([
                'ok' => false,
                'message' => 'Order not found.',
            ], 404);
        }

        $user = $this->getUser();
        if (!$user || $order->getCreatedBy()?->getId() !== $user->getId()) {
            return new JsonResponse([
                'ok' => false,
                'message' => 'You are not allowed to view this order.',
            ], 403);
        }

        return new JsonResponse([
            'ok' => true,
            'message' => 'Order retrieved.',
            'order' => $this->serializeOrder($order),
        ]);
    }

    #[Route('/staff/orders', name: 'api_staff_orders_list', methods: ['GET'])]
    #[IsGranted('ROLE_STAFF')]
    public function listOrdersForStaff(OrderRepository $orders): JsonResponse
    {
        $payload = array_map(
            fn ($order) => $this->serializeOrder($order),
            $orders->findBy([], ['createdAt' => 'DESC'])
        );

        return new JsonResponse([
            'ok' => true,
            'message' => 'Orders retrieved.',
            'orders' => $payload,
        ]);
    }

    private function serializeProduct($product): array
    {
        return [
            'id' => $product->getId(),
            'name' => $product->getName(),
            'description' => $product->getDescription(),
            'price' => (float) $product->getPrice(),
            'image' => $product->getImage(),
            'category' => $product->getCategory(),
        ];
    }

    private function serializeOrder(Order $order): array
    {
        return [
            'id' => $order->getId(),
            'customerName' => $order->getCustomerName(),
            'contact' => $order->getContact(),
            'notes' => $order->getNotes(),
            'totalPrice' => (float) $order->getTotalPrice(),
            'total' => (float) $order->getTotalPrice(),
            'paymentStatus' => $order->getPaymentStatus(),
            'paymentProvider' => $order->getPaymentProvider(),
            'paidAt' => $order->getPaidAt()?->format(DATE_ATOM),
            'status' => $order->getPaymentStatus(),
            'createdAt' => $order->getCreatedAt()?->format(DATE_ATOM),
            'items' => array_map(fn (OrderItem $item) => [
                'id' => $item->getId(),
                'product' => $item->getProduct()
                    ? $this->serializeProduct($item->getProduct())
                    : null,
                'quantity' => $item->getQuantity(),
                'subtotal' => (float) $item->getSubtotal(),
            ], $order->getItems()->toArray()),
        ];
    }
}
