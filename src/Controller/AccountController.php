<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ChangePasswordFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class AccountController extends AbstractController
{
  #[Route('/account/profile', name: 'account_profile')]
public function profile(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $em): Response
{
    /** @var User $user */
    $user = $this->getUser();

    // Change password form 
    $form = $this->createForm(ChangePasswordFormType::class);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $oldPassword = $form->get('oldPassword')->getData();

        if (!$passwordHasher->isPasswordValid($user, $oldPassword)) {
            $this->addFlash('danger', 'Incorrect old password.');
        } else {
            $newPassword = $form->get('newPassword')->getData();
            $user->setPassword($passwordHasher->hashPassword($user, $newPassword));

            $em->flush();

            $this->addFlash('success', 'Password updated successfully.');
            return $this->redirectToRoute('account_profile');
        }
    }

    return $this->render('account/profile.html.twig', [
        'changePasswordForm' => $form->createView(),
    ]);
}


}
