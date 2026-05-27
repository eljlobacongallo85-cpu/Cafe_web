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
                        // Auto-verify users on registration
                        $user->setVerified(true);
                    }

                    try {
                        $em->persist($user);
                        $em->flush();

                        $this->addFlash('success', 'Registration successful. You can now log in.');
                        return $this->redirectToRoute('app_login');
                    } catch (\Throwable) {
                        $em->clear();
                        $error = 'Unable to complete registration. Please try again later.';
                    }
                }
            }
        }

        return $this->render('security/register.html.twig', [
            'error' => $error,
        ]);
    }
}

