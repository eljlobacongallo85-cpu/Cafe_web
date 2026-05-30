<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\ActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ApiAuthController extends AbstractController
{
    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function login(
        Request $request,
        UserRepository $users,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        ActivityLogger $activityLogger
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            $payload = $request->request->all();
        }

        $identifier = trim((string) ($payload['identifier'] ?? $payload['username'] ?? $payload['email'] ?? ''));
        $password = (string) ($payload['password'] ?? '');

        if ($identifier === '' || $password === '') {
            return new JsonResponse([
                'ok' => false,
                'message' => 'Missing credentials.',
            ], 400);
        }

        $user = $users->findOneBy(['username' => $identifier]);
        if (!$user && str_contains($identifier, '@')) {
            $user = $users->findOneBy(['email' => $identifier]);
        }

        if (!$user) {
            return new JsonResponse([
                'ok' => false,
                'message' => 'Invalid credentials.',
            ], 401);
        }

        if (!$hasher->isPasswordValid($user, $password)) {
            return new JsonResponse([
                'ok' => false,
                'message' => 'Invalid credentials.',
            ], 401);
        }

        if (method_exists($user, 'isVerified') && !$user->isVerified()) {
            return new JsonResponse([
                'ok' => false,
                'message' => 'Email not verified.',
            ], 403);
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
        $em->persist($user);
        $em->flush();
        $activityLogger->log($user, 'LOGIN', 'Customer logged in via mobile app');

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
        ]);
    }

    #[Route('/api/logout', name: 'api_logout', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function logout(EntityManagerInterface $em, ActivityLogger $activityLogger): JsonResponse
    {
        $user = $this->getUser();
        if ($user && method_exists($user, 'setApiTokenHash')) {
            if ($user instanceof User) {
                $activityLogger->log($user, 'LOGOUT', 'Customer logged out via mobile app');
            }
            $user->setApiTokenHash(null);
            if (method_exists($user, 'setApiTokenCreatedAt')) {
                $user->setApiTokenCreatedAt(null);
            }
            $em->persist($user);
            $em->flush();
        }

        return new JsonResponse([
            'ok' => true,
            'message' => 'Logged out.',
        ]);
    }

    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function register(
        Request $request,
        EntityManagerInterface $em,
        UserRepository $users,
        UserPasswordHasherInterface $hasher
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            $payload = $request->request->all();
        }

        $email = trim((string) ($payload['email'] ?? ''));
        $password = (string) ($payload['password'] ?? '');
        $username = trim((string) ($payload['username'] ?? ''));
        $name = trim((string) ($payload['name'] ?? ''));

        if ($email === '' || $password === '') {
            return new JsonResponse([
                'ok' => false,
                'message' => 'Email and password are required.',
            ], 400);
        }

        if ($username === '') {
            $atPos = strpos($email, '@');
            $username = $atPos !== false ? substr($email, 0, $atPos) : $email;
        }

        if ($name === '') {
            $name = $username;
        }

        if ($username === '' || $name === '') {
            return new JsonResponse([
                'ok' => false,
                'message' => 'Username and name are required.',
            ], 400);
        }

        if ($users->findOneBy(['username' => $username])) {
            return new JsonResponse([
                'ok' => false,
                'message' => 'Username already exists.',
            ], 409);
        }

        if ($users->findOneBy(['email' => $email])) {
            return new JsonResponse([
                'ok' => false,
                'message' => 'Email already exists.',
            ], 409);
        }

        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setName($name);
        $user->setRoles(['ROLE_CUSTOMER']);
        $user->setPassword($hasher->hashPassword($user, $password));
        if (method_exists($user, 'setVerified')) {
            // Mobile registration uses auto-verification for faster onboarding.
            $user->setVerified(true);
        }

        try {
            $em->persist($user);
            $em->flush();
        } catch (\Throwable $exception) {
            $em->clear();

            return new JsonResponse([
                'ok' => false,
                'message' => 'Unable to complete registration. Please try again later.',
            ], 500);
        }

        return new JsonResponse([
            'ok' => true,
            'message' => 'Registration successful. Your account is verified.',
            'user' => [
                'id' => $user->getId(),
                'username' => $user->getUserIdentifier(),
                'email' => $user->getEmail(),
                'verified' => method_exists($user, 'isVerified') ? $user->isVerified() : true,
                'roles' => $user->getRoles(),
                'name' => $user->getName(),
            ],
        ], 201);
    }
}
