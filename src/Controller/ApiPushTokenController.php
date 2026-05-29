<?php

namespace App\Controller;

use App\Entity\PushToken;
use App\Entity\User;
use App\Repository\PushTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/push-tokens')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class ApiPushTokenController extends AbstractController
{
    #[Route('', name: 'api_push_tokens_store', methods: ['POST'])]
    public function store(
        Request $request,
        PushTokenRepository $pushTokens,
        EntityManagerInterface $em
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            $payload = $request->request->all();
        }

        $token = trim((string) ($payload['token'] ?? ''));
        $platform = strtolower(trim((string) ($payload['platform'] ?? 'android')));

        if ($token === '') {
            return new JsonResponse([
                'ok' => false,
                'message' => 'Push token is required.',
            ], 400);
        }

        if (!in_array($platform, ['android', 'ios', 'web'], true)) {
            $platform = 'android';
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse([
                'ok' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        $pushToken = $pushTokens->findOneBy(['token' => $token]) ?? new PushToken();
        $pushToken
            ->setUser($user)
            ->setToken($token)
            ->setPlatform($platform)
            ->touch();

        $em->persist($pushToken);
        $em->flush();

        return new JsonResponse([
            'ok' => true,
            'message' => 'Push token saved.',
        ]);
    }
}
