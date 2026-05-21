<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api')]
class ApiMeController extends AbstractController
{
    #[Route('/me', name: 'api_me_get', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function me(): JsonResponse
    {
        $user = $this->getUser();

        return new JsonResponse([
            'ok' => true,
            'user' => [
                'id' => method_exists($user, 'getId') ? $user->getId() : null,
                'username' => method_exists($user, 'getUserIdentifier') ? $user->getUserIdentifier() : null,
                'email' => method_exists($user, 'getEmail') ? $user->getEmail() : null,
                'name' => method_exists($user, 'getName') ? $user->getName() : null,
                'roles' => method_exists($user, 'getRoles') ? $user->getRoles() : [],
            ],
        ]);
    }

    #[Route('/me', name: 'api_me_patch', methods: ['PATCH'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function updateMe(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            $payload = $request->request->all();
        }

        $user = $this->getUser();
        if (!$user || !method_exists($user, 'setName')) {
            return new JsonResponse([
                'ok' => false,
                'message' => 'Unable to update profile.',
            ], 400);
        }

        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '') {
            return new JsonResponse([
                'ok' => false,
                'message' => 'Name is required.',
            ], 422);
        }

        $user->setName($name);
        $em->persist($user);
        $em->flush();

        return new JsonResponse([
            'ok' => true,
            'message' => 'Profile updated.',
            'user' => [
                'id' => method_exists($user, 'getId') ? $user->getId() : null,
                'username' => method_exists($user, 'getUserIdentifier') ? $user->getUserIdentifier() : null,
                'email' => method_exists($user, 'getEmail') ? $user->getEmail() : null,
                'name' => method_exists($user, 'getName') ? $user->getName() : null,
                'roles' => method_exists($user, 'getRoles') ? $user->getRoles() : [],
            ],
        ]);
    }
}

