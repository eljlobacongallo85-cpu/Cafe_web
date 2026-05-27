<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class OrderController extends AbstractController
{
    #[Route('/checkout', name: 'checkout', methods: ['GET', 'POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function checkout(Request $request, SessionInterface $session, EntityManagerInterface $em): Response
    {
        $cart = $session->get('cart', []);

        if (empty($cart)) {
            $this->addFlash('warning', 'Your cart is empty.');
            return $this->redirectToRoute('view_cart');
        }

        $total = array_sum(array_map(
            fn($item) => $item['price'] * $item['quantity'], 
            $cart
        ));

        if ($request->isMethod('POST')) {
            $name = $request->request->get('name');
            $contact = $request->request->get('contact');
            $notes = $request->request->get('notes');
            $paymentMethod = (string) $request->request->get('payment_method', 'cash');
            $allowedPaymentMethods = ['cash', 'gcash', 'card'];
            if (!in_array($paymentMethod, $allowedPaymentMethods, true)) {
                $paymentMethod = 'cash';
            }

            $order = new Order();
            $order->setCustomerName($name);
            $order->setContact($contact);
            $order->setNotes($notes);
            $order->setCreatedAt(new \DateTimeImmutable());
            $order->setTotalPrice($total);
            $order->setCreatedBy($this->getUser());
            // Simple built-in payment: mark payment as completed when order is placed.
            $order->setPaymentProvider($paymentMethod);
            $order->setPaymentSessionId(null);
            $order->setPaymentStatus(Order::PAYMENT_STATUS_PAID);
            $order->setPaidAt(new \DateTimeImmutable());

            foreach ($cart as $id => $item) {
                $product = $em->getRepository(Product::class)->find($id);

                if (!$product) continue;

                $orderItem = new OrderItem();
                $orderItem->setProduct($product);
                $orderItem->setQuantity($item['quantity']);
                $orderItem->setSubtotal($item['price'] * $item['quantity']);

                $order->addItem($orderItem);
            }

            $em->persist($order);
            $em->flush();

            $session->remove('cart');

            $this->addFlash('success', 'Payment received. Your order is confirmed.');
            return $this->redirectToRoute('checkout_success');
        }

        return $this->render('cart/checkout.html.twig', [
            'cart' => $cart,
            'total' => $total,
        ]);
    }

    #[Route('/checkout/success', name: 'checkout_success')]
    public function success(): Response
    {
        return $this->render('checkout_success.html.twig');
    }

    #[Route('/my-orders', name: 'my_orders')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function myOrders(EntityManagerInterface $em): Response
    {
        $orders = $em->getRepository(Order::class)->findBy(['createdBy' => $this->getUser()], ['createdAt' => 'DESC']);

        return $this->render('order/index.html.twig', [
            'orders' => $orders,
        ]);
    }
}
