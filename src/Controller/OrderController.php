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

class OrderController extends AbstractController
{
    #[Route('/checkout', name: 'checkout', methods: ['GET', 'POST'])]
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

            $order = new Order();
            $order->setCustomerName($name);
            $order->setContact($contact);
            $order->setNotes($notes);
            $order->setCreatedAt(new \DateTimeImmutable());
            $order->setTotalPrice($total);

            foreach ($cart as $id => $item) {
                $product = $em->getRepository(Product::class)->find($id);

                if (!$product) continue;

                // ⭐ Stock validation
                if ($product->getStock() < $item['quantity']) {
                    $this->addFlash('danger', "Not enough stock for {$product->getName()}.");
                    return $this->redirectToRoute('view_cart');
                }

                $orderItem = new OrderItem();
                $orderItem->setProduct($product);
                $orderItem->setQuantity($item['quantity']);
                $orderItem->setSubtotal($item['price'] * $item['quantity']);

                $order->addItem($orderItem);

                // ⭐ Reduce stock
                $product->setStock($product->getStock() - $item['quantity']);
            }

            $em->persist($order);
            $em->flush();

            $session->remove('cart');

            return $this->redirectToRoute('checkout_success');
        }

        return $this->render('checkout.html.twig', [
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
    public function myOrders(EntityManagerInterface $em): Response
    {
        $orders = $em->getRepository(Order::class)->findBy([], ['createdAt' => 'DESC']);

        return $this->render('order/index.html.twig', [
            'orders' => $orders,
        ]);
    }
}
