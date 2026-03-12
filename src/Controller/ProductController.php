<?php

namespace App\Controller;

use App\Entity\Product;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

class ProductController extends AbstractController
{
    #[Route('/menu', name: 'menu')]
    public function menu(ProductRepository $productRepository): Response
    {
        $products = $productRepository->findAll();

        // Group by category
        $categories = [];
        foreach ($products as $product) {
            $category = $product->getCategory() ?? 'Uncategorized';
            $categories[$category][] = $product;
        }

        return $this->render('menu/index.html.twig', [
            'categories' => $categories,
        ]);
    }

    #[Route('/product/{id}', name: 'product_show')]
    public function show(Product $product): Response
    {
        return $this->render('menu/show.html.twig', [
            'product' => $product,
        ]);
    }

    #[Route('/product/{id}/order', name: 'product_order')]
    public function order(Product $product, SessionInterface $session): Response
    {
        $cart = $session->get('cart', []);
        $id = $product->getId();

        if (isset($cart[$id])) {
            $cart[$id]['quantity']++;
        } else {
            $cart[$id] = [
                'name' => $product->getName(),
                'price' => $product->getPrice(),
                'quantity' => 1,
            ];
        }

        $session->set('cart', $cart);

        $this->addFlash('success', "{$product->getName()} added to your order!");
        return $this->redirectToRoute('view_cart');
    }

    #[Route('/cart', name: 'view_cart')]
    public function viewCart(SessionInterface $session): Response
    {
        $cart = $session->get('cart', []);
        $total = array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $cart));

        return $this->render('cart/index.html.twig', [
            'cart' => $cart,
            'total' => $total,
        ]);
    }

    #[Route('/cart/increase/{id}', name: 'cart_increase')]
    public function increase(int $id, SessionInterface $session): Response
    {
        $cart = $session->get('cart', []);

        if (isset($cart[$id])) $cart[$id]['quantity']++;

        $session->set('cart', $cart);
        return $this->redirectToRoute('view_cart');
    }

    #[Route('/cart/decrease/{id}', name: 'cart_decrease')]
    public function decrease(int $id, SessionInterface $session): Response
    {
        $cart = $session->get('cart', []);

        if (isset($cart[$id])) {
            $cart[$id]['quantity']--;
            if ($cart[$id]['quantity'] <= 0) unset($cart[$id]);
        }

        $session->set('cart', $cart);
        return $this->redirectToRoute('view_cart');
    }

    #[Route('/cart/remove/{id}', name: 'cart_remove')]
    public function remove(int $id, SessionInterface $session): Response
    {
        $cart = $session->get('cart', []);
        unset($cart[$id]);

        $session->set('cart', $cart);
        return $this->redirectToRoute('view_cart');
    }

    #[Route('/cart/clear', name: 'cart_clear')]
    public function clear(SessionInterface $session): Response
    {
        $session->remove('cart');
        return $this->redirectToRoute('view_cart');
    }
}
