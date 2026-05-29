<?php

namespace App\Controller;

use App\Entity\Product;
use App\Entity\ActivityLog;
use App\Form\ProductType;
use App\Repository\ProductRepository;
use App\Service\OrderRealtimePublisher;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/product')]
#[IsGranted('ROLE_STAFF')]
class AdminProductController extends AbstractController
{
    #[Route('/', name: 'admin_product_index')]
    public function index(ProductRepository $productRepository): Response
    {
        return $this->render('admin/product/index.html.twig', [
            'products' => $productRepository->findAll(),
        ]);
    }

    // ✅ ADD PRODUCT
    #[Route('/new', name: 'admin_product_new')]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        OrderRealtimePublisher $realtimePublisher
    ): Response
    {
        $product = new Product();
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getUser();

            if (!$user instanceof \App\Entity\User) {
                throw $this->createAccessDeniedException();
            }

            $product->setCreatedBy($user);
            $em->persist($product);
            $em->flush();
            $realtimePublisher->publishProductUpdated($this->serializeProduct($product));

            // ✅ ACTIVITY LOG
            $log = new ActivityLog();
            $log->setUserFromUser($user);
            $log->setAction('ADD_PRODUCT');
            $log->setTargetData($product->getName());
            $log->setDetails('Added product: ' . $product->getName());
            $log->setCreatedAt(new \DateTimeImmutable());

            $em->persist($log);
            $em->flush();

            $this->addFlash('success', '✅ Product added successfully!');
            return $this->redirectToRoute('admin_product_index');
        }

        return $this->render('admin/product/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    // ✅ EDIT PRODUCT
    #[Route('/{id}/edit', name: 'admin_product_edit')]
    public function edit(
        Request $request,
        Product $product,
        EntityManagerInterface $em,
        OrderRealtimePublisher $realtimePublisher
    ): Response
    {
        $user = $this->getUser();

        if (
            !$this->isGranted('ROLE_ADMIN') &&
            (
                !$user instanceof \App\Entity\User ||
                $product->getCreatedBy()?->getId() !== $user->getId()
            )
        ) {
            throw $this->createAccessDeniedException('⛔ You cannot edit this product.');
        }

        $oldName = $product->getName();

        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $realtimePublisher->publishProductUpdated($this->serializeProduct($product));

            // ✅ ACTIVITY LOG
            $log = new ActivityLog();
            $log->setUserFromUser($user);
            $log->setAction('EDIT_PRODUCT');
            $log->setTargetData($product->getName());
            $log->setDetails("Edited product: $oldName → {$product->getName()}");
            $log->setCreatedAt(new \DateTimeImmutable());

            $em->persist($log);
            $em->flush();

            $this->addFlash('success', '✏️ Product updated successfully!');
            return $this->redirectToRoute('admin_product_index');
        }

        return $this->render('admin/product/edit.html.twig', [
            'form'    => $form->createView(),
            'product' => $product,
        ]);
    }

    // ✅ DELETE PRODUCT
    #[Route('/{id}/delete', name: 'admin_product_delete')]
    public function delete(
        Product $product,
        EntityManagerInterface $em,
        OrderRealtimePublisher $realtimePublisher
    ): Response
    {
        $user = $this->getUser();

        if (
            !$this->isGranted('ROLE_ADMIN') &&
            (
                !$user instanceof \App\Entity\User ||
                $product->getCreatedBy()?->getId() !== $user->getId()
            )
        ) {
            throw $this->createAccessDeniedException('⛔ You cannot delete this product.');
        }

        try {
            $productName = $product->getName();
            $productId = $product->getId();

            $em->remove($product);
            $em->flush();
            $realtimePublisher->publishProductDeleted((int) $productId, (string) $productName);

            // ✅ ACTIVITY LOG
            $log = new ActivityLog();
            $log->setUserFromUser($user);
            $log->setAction('DELETE_PRODUCT');
            $log->setTargetData($productName);
            $log->setDetails('Deleted product: ' . $productName);
            $log->setCreatedAt(new \DateTimeImmutable());

            $em->persist($log);
            $em->flush();

            $this->addFlash('success', '🗑️ Product deleted successfully.');
        } catch (ForeignKeyConstraintViolationException) {
            $this->addFlash(
                'warning',
                '⚠️ This product is linked to existing orders and cannot be deleted.'
            );
        }

        return $this->redirectToRoute('admin_product_index');
    }

    #[Route('/{id}', name: 'admin_product_show', methods: ['GET'])]
    public function show(Product $product): Response
    {
        return $this->render('admin/product/show.html.twig', [
            'product' => $product,
        ]);
    }

    private function serializeProduct(Product $product): array
    {
        $createdBy = $product->getCreatedBy();

        return [
            'id' => $product->getId(),
            'name' => $product->getName(),
            'description' => $product->getDescription(),
            'price' => (float) $product->getPrice(),
            'image' => $product->getImage(),
            'category' => $product->getCategory(),
            'createdBy' => $createdBy ? [
                'id' => $createdBy->getId(),
                'username' => $createdBy->getUserIdentifier(),
            ] : null,
        ];
    }
}
