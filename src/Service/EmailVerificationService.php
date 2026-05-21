<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

class EmailVerificationService
{
    public function __construct(
        private EntityManagerInterface $em,
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
        private Environment $twig
    ) {
    }

    public function startVerification(User $user): void
    {
        if ($user->isVerified()) {
            return;
        }

        if (!$user->getVerificationToken()) {
            $user->setVerificationToken(bin2hex(random_bytes(32)));
        }

        $this->em->persist($user);
        $this->em->flush();

        $verifyUrl = $this->urlGenerator->generate(
            'verify_email',
            ['token' => $user->getVerificationToken()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $body = $this->twig->render('emails/verify_email.html.twig', [
            'user' => $user,
            'verify_url' => $verifyUrl,
        ]);

        $email = (new Email())
            ->from('eljlobacongallo85@gmail.com')
            ->to($user->getEmail())
            ->subject('Verify your email')
            ->html($body);

        $this->mailer->send($email);
    }

    public function verifyToken(string $token): ?User
    {
        $user = $this->em->getRepository(User::class)
            ->findOneBy(['verificationToken' => $token]);

        if (!$user) {
            return null;
        }

        $user->setVerified(true);
        $user->setVerificationToken(null);
        $this->em->flush();

        return $user;
    }
}
