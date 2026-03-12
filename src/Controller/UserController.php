<?php

namespace App\Controller;

use App\Service\ActivityLogger;
use App\Entity\User;
use App\Form\UserType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

    class UserController extends AbstractController
    {
        #[Route('/admin/users', name: 'admin_user_index')]
        public function index(EntityManagerInterface $em): Response
        {
            $this->denyAccessUnlessGranted('ROLE_ADMIN');

            $users = $em->getRepository(User::class)->findBy([], ['createdAt' => 'DESC']);

            return $this->render('admin/user/index.html.twig', [
                'users' => $users,
            ]);
        }

        #[Route('/admin/users/create', name: 'admin_user_create')]
        public function create(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher, ActivityLogger $logger): Response
        {
            $this->denyAccessUnlessGranted('ROLE_ADMIN');

            $user = new User();
            $form = $this->createForm(UserType::class, $user);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $plain = $form->get('password')->getData();
                if ($plain) {
                    $user->setPassword($hasher->hashPassword($user, $plain));
                }
                // status is mapped from form

                $em->persist($user);
                $em->flush();

                $logger->record('CREATE', sprintf('User: %s (ID: %d)', $user->getUserIdentifier(), $user->getId()));

                $this->addFlash('success', 'User created.');
                return $this->redirectToRoute('admin_user_index');
            }

            return $this->render('admin/user/create.html.twig', ['form' => $form->createView()]);
        }

        #[Route('/admin/users/edit/{id}', name: 'admin_user_edit')]
        public function edit(User $user, Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher, ActivityLogger $logger): Response
        {
            $this->denyAccessUnlessGranted('ROLE_ADMIN');

            $form = $this->createForm(UserType::class, $user, ['is_edit' => true]);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $plain = $form->get('password')->getData();
                if ($plain) {
                    $user->setPassword($hasher->hashPassword($user, $plain));
                }

                $em->flush();

                $logger->record('UPDATE', sprintf('User: %s (ID: %d)', $user->getUserIdentifier(), $user->getId()));

                $this->addFlash('success', 'User updated.');
                return $this->redirectToRoute('admin_user_index');
            }

            return $this->render('admin/user/edit.html.twig', ['form' => $form->createView()]);
        }
        #[Route('/admin/users/{id}', name: 'admin_user_show', methods: ['GET'])]
        #[IsGranted('ROLE_ADMIN')]
        public function show(User $user): Response
        {
          return $this->render('admin/user/show.html.twig', [
         'user' => $user,
    ]);
}


        #[Route('/admin/users/delete/{id}', name: 'admin_user_delete', methods:['POST'])]
        public function delete(Request $request, User $user, EntityManagerInterface $em, ActivityLogger $logger): Response
        {
            $this->denyAccessUnlessGranted('ROLE_ADMIN');

            if ($this->isCsrfTokenValid('delete_user_' . $user->getId(), $request->request->get('_token'))) {
                // prevent self-delete
                if ($user === $this->getUser()) {
                    $this->addFlash('warning', 'You cannot delete yourself.');
                    return $this->redirectToRoute('admin_user_index');
                }

                $logger->record('DELETE', sprintf('User: %s (ID: %d)', $user->getUserIdentifier(), $user->getId()));

                $em->remove($user);
                $em->flush();

                $this->addFlash('success', 'User deleted.');
            }

            return $this->redirectToRoute('admin_user_index');
        }

        #[Route('/admin/users/toggle-status/{id}', name: 'admin_user_toggle_status', methods:['POST'])]
        public function toggleStatus(Request $request, User $user, EntityManagerInterface $em, ActivityLogger $logger): Response
        {
            $this->denyAccessUnlessGranted('ROLE_ADMIN');

            if (!$this->isCsrfTokenValid('toggle_status_' . $user->getId(), $request->request->get('_token'))) {
                $this->addFlash('danger', 'Invalid CSRF token.');
                return $this->redirectToRoute('admin_user_index');
            }

            // cycle or toggle between active/disabled (archive separate)
            if ($user->getStatus() === User::STATUS_ACTIVE) {
                $user->setStatus(User::STATUS_DISABLED);
                $action = 'DISABLE';
            } else {
                $user->setStatus(User::STATUS_ACTIVE);
                $action = 'ENABLE';
            }

            $em->flush();
            $logger->record($action, sprintf('User: %s (ID: %d)', $user->getUserIdentifier(), $user->getId()));
            $this->addFlash('success', sprintf('User status changed to %s.', $user->getStatus()));

            return $this->redirectToRoute('admin_user_index');
        }

        #[Route('/admin/users/archive/{id}', name: 'admin_user_archive', methods:['POST'])]
        public function archive(Request $request, User $user, EntityManagerInterface $em, ActivityLogger $logger): Response
        {
            $this->denyAccessUnlessGranted('ROLE_ADMIN');

            if ($this->isCsrfTokenValid('archive_user_' . $user->getId(), $request->request->get('_token'))) {
                $user->setStatus(User::STATUS_ARCHIVED);
                $em->flush();
                $logger->record('ARCHIVE', sprintf('User: %s (ID: %d)', $user->getUserIdentifier(), $user->getId()));
                $this->addFlash('success', 'User archived.');
            }

            return $this->redirectToRoute('admin_user_index');
        }
    }
