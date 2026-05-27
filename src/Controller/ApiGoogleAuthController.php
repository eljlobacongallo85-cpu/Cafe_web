<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/api')]
class ApiGoogleAuthController extends AbstractController
{
    #[Route('/auth/google', name: 'api_auth_google', methods: ['POST'])]
    public function googleLogin(
        Request $request,
        HttpClientInterface $httpClient,
        UserRepository $users,
        EntityManagerInterface $em
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            $payload = $request->request->all();
        }

        $idToken = trim((string) ($payload['idToken'] ?? $payload['token'] ?? ''));
        if ($idToken === '') {
            return new JsonResponse([
                'ok' => false,
                'message' => 'Missing Google idToken.',
            ], 400);
        }

        try {
            $response = $httpClient->request('GET', 'https://oauth2.googleapis.com/tokeninfo', [
                'query' => ['id_token' => $idToken],
            ]);
            $info = $response->toArray(false);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'ok' => false,
                'message' => 'Unable to verify Google token.',
            ], 401);
        }

        $email = trim((string) ($info['email'] ?? ''));
        $aud = (string) ($info['aud'] ?? '');
        $emailVerified = (string) ($info['email_verified'] ?? '');
        $name = trim((string) ($info['name'] ?? ''));

        if ($email === '') {
            return new JsonResponse([
                'ok' => false,
                'message' => 'Google token missing email.',
            ], 401);
        }

        $expectedAud = (string) ($_ENV['OAUTH_GOOGLE_CLIENT_ID'] ?? '');
        if ($expectedAud !== '' && $aud !== '' && $aud !== $expectedAud) {
            return new JsonResponse([
                'ok' => false,
                'message' => 'Google token audience mismatch.',
            ], 401);
        }

        if ($emailVerified !== '' && !in_array(strtolower($emailVerified), ['true', '1'], true)) {
            return new JsonResponse([
                'ok' => false,
                'message' => 'Google email not verified.',
            ], 403);
        }

        $user = $users->findOneBy(['email' => $email]);
        if (!$user) {
            $slugger = new AsciiSlugger();
            $base = (string) $slugger->slug(strstr($email, '@', true) ?: $email)->lower();
            $base = $base !== '' ? $base : 'user';

            $username = $base;
            $suffix = 1;
            while ($users->findOneBy(['username' => $username])) {
                $suffix++;
                $username = $base . $suffix;
            }

            $user = new User();
            $user->setEmail($email);
            $user->setUsername($username);
            $user->setName($name !== '' ? $name : $username);
            $user->setRoles(['ROLE_CUSTOMER']);
            $user->setPassword(bin2hex(random_bytes(16)));
            $user->setVerified(true);

            $em->persist($user);
        }

        if (method_exists($user, 'isActive') && !$user->isActive()) {
            return new JsonResponse([
                'ok' => false,
                'message' => 'Account inactive.',
            ], 403);
        }

        $plainToken = bin2hex(random_bytes(32));
        $user->setApiTokenHash(hash('sha256', $plainToken));
        $user->setApiTokenCreatedAt(new \DateTimeImmutable());
        $em->flush();

        return new JsonResponse([
            'ok' => true,
            'user' => [
                'id' => $user->getId(),
                'username' => $user->getUserIdentifier(),
                'email' => $user->getEmail(),
                'verified' => method_exists($user, 'isVerified') ? $user->isVerified() : true,
                'roles' => $user->getRoles(),
                'name' => $user->getName(),
            ],
            'token' => $plainToken,
            'apiToken' => $plainToken,
            'accessToken' => $plainToken,
            'data' => [
                'user' => [
                    'id' => $user->getId(),
                    'username' => $user->getUserIdentifier(),
                    'email' => $user->getEmail(),
                    'verified' => method_exists($user, 'isVerified') ? $user->isVerified() : true,
                    'roles' => $user->getRoles(),
                    'name' => $user->getName(),
                ],
                'token' => $plainToken,
                'apiToken' => $plainToken,
                'accessToken' => $plainToken,
            ],
        ]);
    }
}

