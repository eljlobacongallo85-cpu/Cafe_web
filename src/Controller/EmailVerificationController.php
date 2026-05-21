<?php

namespace App\Controller;

use App\Service\EmailVerificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class EmailVerificationController extends AbstractController
{
    #[Route('/verify/email', name: 'verify_email', methods: ['GET'])]
    public function verify(Request $request, EmailVerificationService $verification): Response
    {
        $token = (string) $request->query->get('token', '');
        if ($token === '') {
            return $this->render('security/verify_email.html.twig', [
                'status' => 'missing',
            ]);
        }

        $user = $verification->verifyToken($token);
        if (!$user) {
            return $this->render('security/verify_email.html.twig', [
                'status' => 'invalid',
            ]);
        }

        return $this->render('security/verify_email.html.twig', [
            'status' => 'verified',
            'user' => $user,
        ]);
    }

    #[Route('/api/verify/email', name: 'api_verify_email', methods: ['GET'])]
    public function verifyApi(Request $request, EmailVerificationService $verification): JsonResponse
    {
        $token = (string) $request->query->get('token', '');
        if ($token === '') {
            return new JsonResponse([
                'ok' => false,
                'message' => 'Missing token.',
            ], 400);
        }

        $user = $verification->verifyToken($token);
        if (!$user) {
            return new JsonResponse([
                'ok' => false,
                'message' => 'Invalid or expired token.',
            ], 404);
        }

        return new JsonResponse([
            'ok' => true,
            'message' => 'Email verified successfully.',
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'verified' => $user->isVerified(),
            ],
        ]);
    }
}
