<?php

namespace App\Controller;

use App\Entity\Product;
use App\Repository\ProductRepository;
use App\Service\ActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/staff/products')]
#[IsGranted('ROLE_STAFF')]
class ApiStaffProductController extends AbstractController
{
    #[Route('', name: 'api_staff_products_list', methods: ['GET'])]
    public function list(ProductRepository $products): JsonResponse
    {
        $items = $products->findBy([], ['name' => 'ASC']);

        return new JsonResponse([
            'ok' => true,
            'products' => array_map(fn (Product $product) => $this->serializeProduct($product), $items),
        ]);
    }

    #[Route('', name: 'api_staff_products_create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        ValidatorInterface $validator,
        ActivityLogger $logger
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            $payload = $request->request->all();
        }

        $user = $this->getUser();
        if (!$user instanceof \App\Entity\User) {
            return new JsonResponse(['ok' => false, 'message' => 'Unauthorized.'], 401);
        }

        $product = new Product();
        $product->setName(trim((string) ($payload['name'] ?? '')));
        $product->setDescription(($payload['description'] ?? null) !== null ? trim((string) $payload['description']) : null);
        $product->setCategory(($payload['category'] ?? null) !== null ? trim((string) $payload['category']) : null);
        $product->setImage(($payload['image'] ?? null) !== null ? trim((string) $payload['image']) : null);
        $product->setPrice((float) ($payload['price'] ?? 0));
        $product->setCreatedBy($user);

        $errors = $validator->validate($product);
        if (count($errors) > 0) {
            return new JsonResponse([
                'ok' => false,
                'message' => 'Validation failed.',
                'errors' => array_map(static fn ($e) => [
                    'field' => $e->getPropertyPath(),
                    'message' => $e->getMessage(),
                ], iterator_to_array($errors)),
            ], 422);
        }

        $em->persist($product);
        $em->flush();

        $logger->record('ADD_PRODUCT', sprintf('Product #%d created via API', $product->getId()));

        return new JsonResponse([
            'ok' => true,
            'message' => 'Product created.',
            'product' => $this->serializeProduct($product),
        ], 201);
    }

    #[Route('/{id}', name: 'api_staff_products_get', methods: ['GET'])]
    public function getOne(Product $product): JsonResponse
    {
        return new JsonResponse([
            'ok' => true,
            'product' => $this->serializeProduct($product),
        ]);
    }

    #[Route('/{id}', name: 'api_staff_products_patch', methods: ['PATCH'])]
    public function patch(
        Product $product,
        Request $request,
        EntityManagerInterface $em,
        ValidatorInterface $validator,
        ActivityLogger $logger
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user instanceof \App\Entity\User) {
            return new JsonResponse(['ok' => false, 'message' => 'Unauthorized.'], 401);
        }

        if (
            !$this->isGranted('ROLE_ADMIN') &&
            $product->getCreatedBy()?->getId() !== $user->getId()
        ) {
            return new JsonResponse(['ok' => false, 'message' => 'Forbidden.'], 403);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            $payload = $request->request->all();
        }

        if (array_key_exists('name', $payload)) {
            $product->setName(trim((string) $payload['name']));
        }
        if (array_key_exists('description', $payload)) {
            $product->setDescription(($payload['description'] ?? null) !== null ? trim((string) $payload['description']) : null);
        }
        if (array_key_exists('category', $payload)) {
            $product->setCategory(($payload['category'] ?? null) !== null ? trim((string) $payload['category']) : null);
        }
        if (array_key_exists('image', $payload)) {
            $product->setImage(($payload['image'] ?? null) !== null ? trim((string) $payload['image']) : null);
        }
        if (array_key_exists('price', $payload)) {
            $product->setPrice((float) $payload['price']);
        }

        $errors = $validator->validate($product);
        if (count($errors) > 0) {
            return new JsonResponse([
                'ok' => false,
                'message' => 'Validation failed.',
                'errors' => array_map(static fn ($e) => [
                    'field' => $e->getPropertyPath(),
                    'message' => $e->getMessage(),
                ], iterator_to_array($errors)),
            ], 422);
        }

        $em->flush();
        $logger->record('EDIT_PRODUCT', sprintf('Product #%d updated via API', $product->getId()));

        return new JsonResponse([
            'ok' => true,
            'message' => 'Product updated.',
            'product' => $this->serializeProduct($product),
        ]);
    }

    #[Route('/{id}', name: 'api_staff_products_delete', methods: ['DELETE'])]
    public function delete(
        Product $product,
        EntityManagerInterface $em,
        ActivityLogger $logger
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user instanceof \App\Entity\User) {
            return new JsonResponse(['ok' => false, 'message' => 'Unauthorized.'], 401);
        }

        if (
            !$this->isGranted('ROLE_ADMIN') &&
            $product->getCreatedBy()?->getId() !== $user->getId()
        ) {
            return new JsonResponse(['ok' => false, 'message' => 'Forbidden.'], 403);
        }

        $id = $product->getId();
        $name = $product->getName();

        try {
            $em->remove($product);
            $em->flush();
        } catch (\Throwable $e) {
            return new JsonResponse([
                'ok' => false,
                'message' => 'Unable to delete product (it may be linked to orders).',
            ], 409);
        }

        $logger->record('DELETE_PRODUCT', sprintf('Product #%d (%s) deleted via API', $id, $name));

        return new JsonResponse([
            'ok' => true,
            'message' => 'Product deleted.',
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

