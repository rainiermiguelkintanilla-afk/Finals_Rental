<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use App\Repository\UserRepository;
use App\Service\ActivityLogService;
use App\Entity\ActivityLog;
use App\Repository\ActivityLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dashboard/users')]
#[IsGranted('ROLE_ADMIN')]
class UserController extends AbstractController
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
        private ActivityLogService $activityLogService
    ) {
    }

    #[Route('/', name: 'app_user_index', methods: ['GET'])]
    public function index(UserRepository $userRepository): Response
    {
        return $this->render('dashboard/user/index.html.twig', [
            'users' => $userRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_user_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = new User();
        $form = $this->createForm(UserType::class, $user, ['is_edit' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Hash password if provided, otherwise use default
            $plainPassword = $form->get('password')->getData();
            if (!$plainPassword) {
                $plainPassword = 'sazsad'; // Default password
            }
            $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));

            $entityManager->persist($user);
            $entityManager->flush();

            // Log activity
            $this->activityLogService->logCreate(
                $this->getUser(),
                'User',
                $user->getId(),
                "Created user: {$user->getEmail()}"
            );

            $this->addFlash('success', 'User created successfully!');
            return $this->redirectToRoute('app_dashboard_index');
        }

        return $this->render('dashboard/user/new.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_user_show', methods: ['GET'])]
    public function show(User $user, ActivityLogRepository $activityLogRepository): Response
    {
        // count activity logs for display via repository
        $activityLogCount = $activityLogRepository->count(['user' => $user]);

        return $this->render('dashboard/user/show.html.twig', [
            'user' => $user,
            'activityLogCount' => $activityLogCount,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_user_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {
        // Prevent deleting the last admin
        $isLastAdmin = $user->isAdmin() && count(array_filter(
            $entityManager->getRepository(User::class)->findAll(),
            fn($u) => $u->isAdmin()
        )) === 1;

        $form = $this->createForm(UserType::class, $user, ['is_edit' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Update password if provided
            $plainPassword = $form->get('password')->getData();
            if ($plainPassword) {
                $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
            }

            $entityManager->flush();

            // Log activity
            $this->activityLogService->logUpdate(
                $this->getUser(),
                'User',
                $user->getId(),
                "Updated user: {$user->getEmail()}"
            );

            $this->addFlash('success', 'User updated successfully!');
            return $this->redirectToRoute('app_user_index');
        }

        return $this->render('dashboard/user/edit.html.twig', [
            'user' => $user,
            'form' => $form,
            'is_last_admin' => $isLastAdmin,
        ]);
    }

    #[Route('/{id}', name: 'app_user_delete', methods: ['POST'])]
    public function delete(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('delete'.$user->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('app_user_index');
        }

        // Prevent deleting yourself
        $currentUser = $this->getUser();
        if ($currentUser instanceof User && $user->getId() === $currentUser->getId()) {
            $this->addFlash('error', 'You cannot delete your own account.');
            return $this->redirectToRoute('app_user_index');
        }

        // Prevent deleting the last admin
        $allUsers = $entityManager->getRepository(User::class)->findAll();
        $adminCount = count(array_filter($allUsers, fn($u) => $u->isAdmin()));
        if ($user->isAdmin() && $adminCount <= 1) {
            $this->addFlash('error', 'Cannot delete the last admin user.');
            return $this->redirectToRoute('app_user_index');
        }

        // Prevent deleting a user that has activity logs (foreign key constraint)
        $activityLogCount = $entityManager->getRepository(ActivityLog::class)->count(['user' => $user]);
        if ($activityLogCount > 0) {
            $this->addFlash('error', sprintf('Cannot delete user because they have %d activity log(s). Remove or reassign those logs first.', $activityLogCount));
            return $this->redirectToRoute('app_user_show', ['id' => $user->getId()]);
        }

        $userId = $user->getId();
        $userEmail = $user->getEmail();

        $entityManager->remove($user);
        $entityManager->flush();

        // Log activity
        $this->activityLogService->logDelete(
            $this->getUser(),
            'User',
            $userId,
            "Deleted user: {$userEmail}"
        );

        $this->addFlash('success', 'User deleted successfully!');
        return $this->redirectToRoute('app_user_index');
    }

    #[Route('/{id}/change-password', name: 'app_user_change_password', methods: ['GET', 'POST'])]
    public function changePassword(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {
        if ($request->isMethod('POST')) {
            $newPassword = $request->request->get('newPassword');
            $confirmPassword = $request->request->get('confirmPassword');
            
            if (!$this->isCsrfTokenValid('change_password'.$user->getId(), $request->request->get('_token'))) {
                $this->addFlash('error', 'Invalid security token.');
                return $this->redirectToRoute('app_user_show', ['id' => $user->getId()]);
            }
            
            if (!$newPassword || !$confirmPassword) {
                $this->addFlash('error', 'Both password fields are required.');
                return $this->redirectToRoute('app_user_show', ['id' => $user->getId()]);
            }
            
            if ($newPassword !== $confirmPassword) {
                $this->addFlash('error', 'New password and confirmation do not match.');
                return $this->redirectToRoute('app_user_show', ['id' => $user->getId()]);
            }
            
            if (strlen($newPassword) < 6) {
                $this->addFlash('error', 'Password must be at least 6 characters long.');
                return $this->redirectToRoute('app_user_show', ['id' => $user->getId()]);
            }
            
            // Update password
            $user->setPassword($this->passwordHasher->hashPassword($user, $newPassword));
            $entityManager->flush();
            
            // Log activity
            $this->activityLogService->logUpdate(
                $this->getUser(),
                'User',
                $user->getId(),
                "Admin changed password for user: {$user->getEmail()}"
            );
            
            $this->addFlash('success', 'Password changed successfully!');
            return $this->redirectToRoute('app_user_show', ['id' => $user->getId()]);
        }
        
        return $this->render('dashboard/user/change_password.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/{id}/force-delete', name: 'app_user_force_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function forceDelete(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('force_delete'.$user->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('app_user_show', ['id' => $user->getId()]);
        }

        // Prevent deleting yourself
        $currentUser = $this->getUser();
        if ($currentUser instanceof User && $user->getId() === $currentUser->getId()) {
            $this->addFlash('error', 'You cannot delete your own account.');
            return $this->redirectToRoute('app_user_show', ['id' => $user->getId()]);
        }

        // Prevent deleting the last admin
        $allUsers = $entityManager->getRepository(User::class)->findAll();
        $adminCount = count(array_filter($allUsers, fn($u) => $u->isAdmin()));
        if ($user->isAdmin() && $adminCount <= 1) {
            $this->addFlash('error', 'Cannot delete the last admin user.');
            return $this->redirectToRoute('app_user_show', ['id' => $user->getId()]);
        }

        // Delete activity logs for the user (bulk DQL delete)
        $qb = $entityManager->createQueryBuilder();
        $qb->delete(\App\Entity\ActivityLog::class, 'a')
           ->where('a.user = :user')
           ->setParameter('user', $user);
        $qb->getQuery()->execute();

        // Now remove the user
        $userId = $user->getId();
        $userEmail = $user->getEmail();

        $entityManager->remove($user);
        $entityManager->flush();

        // Log activity (record who performed the deletion)
        $this->activityLogService->logDelete(
            $this->getUser(),
            'User',
            $userId,
            "Force-deleted user and their logs: {$userEmail}"
        );

        $this->addFlash('success', 'User and their activity logs have been deleted successfully.');
        return $this->redirectToRoute('app_user_index');
    }
}

