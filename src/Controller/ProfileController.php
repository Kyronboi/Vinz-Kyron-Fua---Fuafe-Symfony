<?php

namespace App\Controller;

use App\Entity\Customer;
use App\Repository\CartRepository;
use App\Repository\CommentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'app_profile', methods: ['GET'])]
    public function index(CartRepository $cartRepo, CommentRepository $commentRepo): Response
    {
        /** @var Customer $user */
        $user = $this->getUser();

        $cartItems = $cartRepo->findBy(['customer' => $user]);
        $userComments = $commentRepo->findBy(['customer' => $user], ['createdAt' => 'DESC']);

        $cartTotal = 0.0;
        foreach ($cartItems as $item) {
            $cartTotal += (float) $item->getPrice() * $item->getQuantity();
        }

        return $this->render('profile/index.html.twig', [
            'user' => $user,
            'cartItems' => $cartItems,
            'cartTotal' => $cartTotal,
            'userComments' => $userComments,
        ]);
    }

    #[Route('/profile/update', name: 'app_profile_update', methods: ['POST'])]
    public function updateProfile(Request $request, EntityManagerInterface $em): Response
    {
        /** @var Customer $user */
        $user = $this->getUser();

        $payload = $request->getPayload();
        $token = $payload->getString('_csrf_token');

        if (!$this->isCsrfTokenValid('profile_update', $token)) {
            $this->addFlash('error', 'Invalid security token. Please try again.');
            return $this->redirectToRoute('app_profile');
        }

        $username = trim($payload->getString('username'));
        $location = trim($payload->getString('location'));
        $age = $payload->getInt('age', 0);

        if ($username === '' || $location === '') {
            $this->addFlash('error', 'Username and delivery location are required.');
            return $this->redirectToRoute('app_profile');
        }

        if ($age < 1 || $age > 100) {
            $this->addFlash('error', 'Age cannot be over 100 years old (must be between 1 and 100).');
            return $this->redirectToRoute('app_profile');
        }

        $user->setUsername($username);
        $user->setLocation($location);
        $user->setAge($age);

        $em->flush();

        $this->addFlash('success', 'Your profile details have been updated successfully!');
        return $this->redirectToRoute('app_profile');
    }

    #[Route('/profile/change-password', name: 'app_profile_change_password', methods: ['POST'])]
    public function changePassword(
        Request $request,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $em
    ): Response {
        /** @var Customer $user */
        $user = $this->getUser();

        $payload = $request->getPayload();
        $token = $payload->getString('_csrf_token');

        if (!$this->isCsrfTokenValid('profile_password', $token)) {
            $this->addFlash('error', 'Invalid security token. Please try again.');
            return $this->redirectToRoute('app_profile');
        }

        $currentPassword = $payload->getString('current_password');
        $newPassword = $payload->getString('new_password');
        $confirmPassword = $payload->getString('confirm_password');

        if (!$hasher->isPasswordValid($user, $currentPassword)) {
            $this->addFlash('error', 'Current password is incorrect.');
            return $this->redirectToRoute('app_profile');
        }

        if (strlen($newPassword) < 6) {
            $this->addFlash('error', 'New password must be at least 6 characters long.');
            return $this->redirectToRoute('app_profile');
        }

        if ($newPassword !== $confirmPassword) {
            $this->addFlash('error', 'New password and confirmation do not match.');
            return $this->redirectToRoute('app_profile');
        }

        $user->setPassword($hasher->hashPassword($user, $newPassword));
        $em->flush();

        $this->addFlash('success', 'Your password has been changed successfully!');
        return $this->redirectToRoute('app_profile');
    }
}
