<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class LoginFormAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private UserRepository $users
    ) {}

    public function authenticate(Request $request): Passport
{
    $identifier = trim((string) $request->request->get('username', ''));

    $request->getSession()->set(
        SecurityRequestAttributes::LAST_USERNAME,
        $identifier
    );

    return new Passport(
        new UserBadge($identifier, function (string $identifier): ?User {
            $user = $this->users->findOneBy(['username' => $identifier]);
            if (!$user && str_contains($identifier, '@')) {
                $user = $this->users->findOneBy(['email' => $identifier]);
            }
            if (!$user) {
                throw new CustomUserMessageAuthenticationException('Invalid credentials.');
            }
            if (method_exists($user, 'isActive') && !$user->isActive()) {
                throw new CustomUserMessageAuthenticationException('Your account is not active. Contact an administrator.');
            }
            if (method_exists($user, 'isVerified') && !$user->isVerified()) {
                throw new CustomUserMessageAuthenticationException('Please verify your email before logging in.');
            }
            return $user;
        }),
        new PasswordCredentials(
            $request->request->get('password', '')
        ),
        [
            new CsrfTokenBadge(
                'authenticate',
                $request->request->get('_csrf_token')
            ),
            new RememberMeBadge(),
        ]
    );
}


    public function onAuthenticationSuccess(
        Request $request,
        TokenInterface $token,
        string $firewallName
    ): ?Response {
        /** @var User $user */
        $user = $token->getUser();

        // Redirect to saved target path
        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            $loginUrl = $this->urlGenerator->generate(self::LOGIN_ROUTE);
            $targetPathPath = parse_url($targetPath, PHP_URL_PATH) ?: $targetPath;
            if ($targetPathPath !== $loginUrl) {
                return new RedirectResponse($targetPath);
            }
        }

        $roles = $token->getRoleNames();

        if (in_array('ROLE_ADMIN', $roles, true)) {
            return new RedirectResponse(
                $this->urlGenerator->generate('admin_dashboard')
            );
        }

        if (in_array('ROLE_STAFF', $roles, true)) {
            return new RedirectResponse(
                $this->urlGenerator->generate('staff_dashboard')
            );
        }

        if (in_array('ROLE_CUSTOMER', $roles, true)) {
            return new RedirectResponse(
                $this->urlGenerator->generate('homepage')
            );
        }

        // Normal users / fallback
        return new RedirectResponse(
            $this->urlGenerator->generate('homepage')
        );
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}
