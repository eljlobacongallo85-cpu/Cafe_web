<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use League\OAuth2\Client\Provider\GoogleUser;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
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
        private \Doctrine\ORM\EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher
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

        $googleName = (string) ($googleData['name'] ?? '');
        if (trim($googleName) === '') {
            $googleName = (string) ($googleData['given_name'] ?? '');
        }

        return new SelfValidatingPassport(
            new UserBadge($email, function (string $userIdentifier) use ($googleName): UserInterface {
                $user = $this->users->findOneBy(['email' => $userIdentifier]);

                if (!$user) {
                    // Auto-create a customer account for first-time Google sign-in
                    $user = new User();
                    $user->setEmail($userIdentifier);

                    $atPos = strpos($userIdentifier, '@');
                    $baseUsername = $atPos !== false ? substr($userIdentifier, 0, $atPos) : $userIdentifier;
                    $baseUsername = trim($baseUsername) !== '' ? $baseUsername : 'customer';
                    $candidate = $baseUsername;
                    for ($i = 0; $i < 50; $i++) {
                        if (!$this->users->findOneBy(['username' => $candidate])) {
                            break;
                        }
                        $candidate = $baseUsername . '_' . random_int(1000, 9999);
                    }
                    if ($this->users->findOneBy(['username' => $candidate])) {
                        $candidate = 'customer_' . bin2hex(random_bytes(4));
                    }

                    $user->setUsername($candidate);
                    $user->setName(trim($googleName) !== '' ? $googleName : $candidate);
                    $user->setRoles(['ROLE_CUSTOMER']);
                    $user->setPassword($this->hasher->hashPassword($user, bin2hex(random_bytes(24))));
                    $user->setVerified(true);
                    $user->setVerificationToken(null);

                    $this->em->persist($user);
                    $this->em->flush();
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

        if (in_array('ROLE_ADMIN', $roles, true)) {
            return new RedirectResponse($this->router->generate('admin_dashboard'));
        }

        if (in_array('ROLE_STAFF', $roles, true)) {
            return new RedirectResponse($this->router->generate('staff_dashboard'));
        }

        return new RedirectResponse($this->router->generate('homepage'));
    }

    public function onAuthenticationFailure(Request $request, \Throwable $exception): ?Response
    {
        if ($exception instanceof CustomUserMessageAuthenticationException) {
            $request->getSession()->getFlashBag()->add('danger', $exception->getMessage());
        }

        return new RedirectResponse($this->router->generate('app_login'));
    }
}
