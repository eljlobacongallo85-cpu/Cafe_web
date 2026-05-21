<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\EmailVerificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        EntityManagerInterface $em,
        UserRepository $users,
        UserPasswordHasherInterface $hasher,
        EmailVerificationService $verification
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('homepage');
        }

        $error = null;

        if ($request->isMethod('POST')) {
            $email = trim((string) $request->request->get('email', ''));
            $username = trim((string) $request->request->get('username', ''));
            $name = trim((string) $request->request->get('name', ''));
            $password = (string) $request->request->get('password', '');
            $confirmPassword = (string) $request->request->get('confirm_password', '');

            if ($email === '' || $password === '' || $confirmPassword === '') {
                $error = 'Email and password are required.';
            } elseif ($password !== $confirmPassword) {
                $error = 'Password and confirm password must match.';
            } else {
                if ($username === '') {
                    $atPos = strpos($email, '@');
                    $username = $atPos !== false ? substr($email, 0, $atPos) : $email;
                }
                if ($name === '') {
                    $name = $username;
                }

                if ($users->findOneBy(['username' => $username])) {
                    $error = 'Username already exists.';
                } elseif ($users->findOneBy(['email' => $email])) {
                    $error = 'Email already exists.';
                } else {
                    $user = new User();
                    $user->setUsername($username);
                    $user->setEmail($email);
                    $user->setName($name);
                    $user->setRoles(['ROLE_CUSTOMER']);
                    $user->setPassword($hasher->hashPassword($user, $password));
                    if (method_exists($user, 'setVerified')) {
                        $user->setVerified(false);
                    }

                    $connection = $em->getConnection();
                    $connection->beginTransaction();

                    try {
                        $em->persist($user);
                        $em->flush();

                        $verification->startVerification($user);

                        $connection->commit();

                        $this->addFlash('success', 'Registration successful. Please check your email to verify your account.');
                        return $this->redirectToRoute('app_login');
                    } catch (\Throwable) {
                        $connection->rollBack();
                        $em->clear();
                        $error = 'Unable to send the verification email. Please try again later.';
                    }
                }
            }
        }

        return $this->render('security/register.html.twig', [
            'error' => $error,
        ]);
    }
}

