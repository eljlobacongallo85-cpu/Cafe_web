<?php

namespace App\Security;

use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class ApiTokenAuthenticator extends AbstractAuthenticator
{
    private const TOKEN_TTL_DAYS = 30;

    public function __construct(private readonly UserRepository $users)
    {
    }

    public function supports(Request $request): ?bool
    {
        $publicApiPaths = [
            '/api/login',
            '/api/register',
            '/api/hello',
            '/api/products',
            '/api/verify/email',
            '/api/auth/google',
        ];

        return str_starts_with((string) $request->getPathInfo(), '/api')
            && !in_array($request->getPathInfo(), $publicApiPaths, true);
    }

    public function authenticate(Request $request): SelfValidatingPassport
    {
        $authHeader = (string) $request->headers->get('Authorization', '');
        if (!str_starts_with($authHeader, 'Bearer ')) {
            throw new CustomUserMessageAuthenticationException('Missing API token.');
        }

        $token = trim(substr($authHeader, 7));
        if ($token === '') {
            throw new CustomUserMessageAuthenticationException('Missing API token.');
        }

        $tokenHash = hash('sha256', $token);

        return new SelfValidatingPassport(
            new UserBadge($tokenHash, function (string $tokenHash) {
                $user = $this->users->findOneByApiTokenHash($tokenHash);
                if (!$user) {
                    throw new CustomUserMessageAuthenticationException('Invalid API token.');
                }
                $createdAt = method_exists($user, 'getApiTokenCreatedAt') ? $user->getApiTokenCreatedAt() : null;
                if ($createdAt instanceof \DateTimeImmutable) {
                    $expiresAt = $createdAt->modify('+' . self::TOKEN_TTL_DAYS . ' days');
                    if ($expiresAt < new \DateTimeImmutable()) {
                        throw new CustomUserMessageAuthenticationException('API token expired. Please login again.');
                    }
                }
                if (method_exists($user, 'isVerified') && !$user->isVerified()) {
                    throw new CustomUserMessageAuthenticationException('Email not verified.');
                }
                if (method_exists($user, 'isActive') && !$user->isActive()) {
                    throw new CustomUserMessageAuthenticationException('Account inactive.');
                }
                return $user;
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse([
            'ok' => false,
            'message' => $exception->getMessage(),
        ], 401);
    }
}
