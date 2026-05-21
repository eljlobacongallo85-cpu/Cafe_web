<?php

namespace App\Security;

use App\Repository\UserRepository;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use League\OAuth2\Client\Provider\GoogleUser;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class GoogleAuthenticator extends OAuth2Authenticator
{
    public function __construct(
        private ClientRegistry $clientRegistry,
        private UserRepository $users,
        private RouterInterface $router,
        private \Doctrine\ORM\EntityManagerInterface $em
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return $request->attributes->get('_route') === 'connect_google_check';
    }

    public function authenticate(Request $request): SelfValidatingPassport
    {
        $client = $this->clientRegistry->getClient('google');
        $accessToken = $this->fetchAccessToken($client);
        $googleUser = $client->fetchUserFromToken($accessToken);

        if (!$googleUser instanceof GoogleUser) {
            throw new CustomUserMessageAuthenticationException('Unable to read Google account details.');
        }

        $email = $googleUser->getEmail();
        if (!$email) {
            throw new CustomUserMessageAuthenticationException('Your Google account has no email address.');
        }

        $googleData = $googleUser->toArray();
        $emailVerified = (bool) ($googleData['email_verified'] ?? false);
        if (!$emailVerified) {
            throw new CustomUserMessageAuthenticationException('Your Google email is not verified.');
        }

        return new SelfValidatingPassport(
            new UserBadge($email, function (string $userIdentifier): UserInterface {
                $user = $this->users->findOneBy(['email' => $userIdentifier]);

                if (!$user) {
                    throw new CustomUserMessageAuthenticationException('No staff account found for this Google email.');
                }

                $roles = $user->getRoles();
                $isStaff = in_array('ROLE_STAFF', $roles, true) || in_array('ROLE_ADMIN', $roles, true);
                if (!$isStaff) {
                    throw new CustomUserMessageAuthenticationException('Google login is available to staff only.');
                }

                if (method_exists($user, 'isActive') && !$user->isActive()) {
                    throw new CustomUserMessageAuthenticationException('Your account is inactive.');
                }

                if (method_exists($user, 'isVerified') && !$user->isVerified()) {
                    $user->setVerified(true);
                    $user->setVerificationToken(null);
                    $this->em->flush();
                }

                return $user;
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $roles = $token->getRoleNames();
        $route = in_array('ROLE_ADMIN', $roles, true) ? 'admin_dashboard' : 'staff_dashboard';

        return new RedirectResponse($this->router->generate($route));
    }

    public function onAuthenticationFailure(Request $request, \Throwable $exception): ?Response
    {
        if ($exception instanceof CustomUserMessageAuthenticationException) {
            $request->getSession()->getFlashBag()->add('danger', $exception->getMessage());
        }

        return new RedirectResponse($this->router->generate('app_login'));
    }
}
