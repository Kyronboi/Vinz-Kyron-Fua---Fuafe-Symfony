<?php

namespace App\Controller;

use App\Entity\Customer;
use App\Entity\Product;
use App\Entity\Comment;
use App\Repository\CartRepository;
use App\Repository\CommentRepository;
use App\Repository\CustomerRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin')]
final class AdminController extends AbstractController
{
    #[Route('', name: 'app_admin', methods: ['GET'])]
    public function index(
        CustomerRepository $customerRepo,
        ProductRepository $productRepo,
        CommentRepository $commentRepo
    ): Response {
        $allUsers = $customerRepo->findAll();
        $allProducts = $productRepo->findAll();
        $allComments = $commentRepo->findAll();

        $stats = [
            'total_users' => count($allUsers),
            'customers' => 0,
            'staff' => 0,
            'admins' => 0,
            'inactive_users' => 0,
            'total_products' => count($allProducts),
            'low_stock_products' => 0,
            'total_comments' => count($allComments),
            'hidden_comments' => 0,
        ];

        foreach ($allUsers as $u) {
            $roles = $u->getRoles();
            if (in_array('ROLE_ADMIN', $roles, true)) {
                $stats['admins']++;
            } elseif (in_array('ROLE_STAFF', $roles, true)) {
                $stats['staff']++;
            } else {
                $stats['customers']++;
            }
            if (!$u->isActive()) {
                $stats['inactive_users']++;
            }
        }

        foreach ($allProducts as $p) {
            if ($p->getStock() !== null && $p->getStock() < 15) {
                $stats['low_stock_products']++;
            }
        }

        foreach ($allComments as $c) {
            if ($c->isHidden()) {
                $stats['hidden_comments']++;
            }
        }

        return $this->render('admin/index.html.twig', [
            'stats' => $stats,
            'recentUsers' => array_slice(array_reverse($allUsers), 0, 5),
            'recentComments' => array_slice(array_reverse($allComments), 0, 5),
        ]);
    }

    #[Route('/users', name: 'app_admin_users', methods: ['GET'])]
    public function users(CustomerRepository $customerRepo): Response
    {
        $users = $customerRepo->findBy([], ['id' => 'ASC']);

        return $this->render('admin/users/index.html.twig', [
            'users' => $users,
        ]);
    }

    #[Route('/users/new', name: 'app_admin_user_new', methods: ['GET', 'POST'])]
    public function newUser(
        Request $request,
        CustomerRepository $customerRepo,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher
    ): Response {
        if ($request->isMethod('POST')) {
            $payload = $request->getPayload();
            $token = $payload->getString('_csrf_token');

            if (!$this->isCsrfTokenValid('admin_user_new', $token)) {
                $this->addFlash('error', 'Invalid security token.');
                return $this->redirectToRoute('app_admin_user_new');
            }

            $email = trim(strtolower($payload->getString('email')));
            $username = trim($payload->getString('username'));
            $role = $payload->getString('role', 'ROLE_USER');
            $password = $payload->getString('password');
            $age = $payload->getInt('age', 25);
            $location = trim($payload->getString('location')) ?: 'Dumaguete City';

            if ($email === '' || $username === '' || $password === '') {
                $this->addFlash('error', 'Username, email, and password are required.');
                return $this->render('admin/users/new.html.twig', ['formData' => $payload->all()]);
            }

            if ($age < 1 || $age > 100) {
                $this->addFlash('error', 'Age must be between 1 and 100 years old.');
                return $this->render('admin/users/new.html.twig', ['formData' => $payload->all()]);
            }

            if ($customerRepo->findOneBy(['email' => $email])) {
                $this->addFlash('error', 'An account with this email address already exists.');
                return $this->render('admin/users/new.html.twig', ['formData' => $payload->all()]);
            }

            $user = new Customer();
            $user->setEmail($email);
            $user->setUsername($username);
            $user->setAge($age);
            $user->setLocation($location);
            $user->setRoles([$role]);
            $user->setIsActive(true);
            $user->setCreatedAt(new \DateTimeImmutable());
            $user->setPassword($hasher->hashPassword($user, $password));

            $em->persist($user);
            $em->flush();

            $this->addFlash('success', sprintf('User "%s" created successfully with role %s.', $username, $role));
            return $this->redirectToRoute('app_admin_users');
        }

        return $this->render('admin/users/new.html.twig', ['formData' => []]);
    }

    #[Route('/users/{id}/edit', name: 'app_admin_user_edit', methods: ['GET', 'POST'])]
    public function editUser(
        int $id,
        Request $request,
        CustomerRepository $customerRepo,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher
    ): Response {
        $user = $customerRepo->find($id);
        if (!$user) {
            throw $this->createNotFoundException('User not found.');
        }

        if ($request->isMethod('POST')) {
            $payload = $request->getPayload();
            $token = $payload->getString('_csrf_token');

            if (!$this->isCsrfTokenValid('admin_user_edit_' . $user->getId(), $token)) {
                $this->addFlash('error', 'Invalid security token.');
                return $this->redirectToRoute('app_admin_user_edit', ['id' => $user->getId()]);
            }

            $email = trim(strtolower($payload->getString('email')));
            $username = trim($payload->getString('username'));
            $role = $payload->getString('role', 'ROLE_USER');
            $newPassword = $payload->getString('password');
            $age = $payload->getInt('age', $user->getAge() ?? 25);
            $location = trim($payload->getString('location')) ?: $user->getLocation();

            if ($email === '' || $username === '') {
                $this->addFlash('error', 'Username and email cannot be empty.');
                return $this->redirectToRoute('app_admin_user_edit', ['id' => $user->getId()]);
            }

            if ($age < 1 || $age > 100) {
                $this->addFlash('error', 'Age must be between 1 and 100 years old.');
                return $this->redirectToRoute('app_admin_user_edit', ['id' => $user->getId()]);
            }

            $existing = $customerRepo->findOneBy(['email' => $email]);
            if ($existing && $existing->getId() !== $user->getId()) {
                $this->addFlash('error', 'Another account already uses this email.');
                return $this->redirectToRoute('app_admin_user_edit', ['id' => $user->getId()]);
            }

            $user->setEmail($email);
            $user->setUsername($username);
            $user->setAge($age);
            $user->setLocation($location);
            $user->setRoles([$role]);

            if ($newPassword !== '') {
                $user->setPassword($hasher->hashPassword($user, $newPassword));
            }

            $em->flush();

            $this->addFlash('success', sprintf('User "%s" updated successfully.', $user->getUsername()));
            return $this->redirectToRoute('app_admin_users');
        }

        return $this->render('admin/users/edit.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/users/{id}/toggle-active', name: 'app_admin_user_toggle_active', methods: ['POST'])]
    public function toggleUserActive(
        int $id,
        Request $request,
        CustomerRepository $customerRepo,
        EntityManagerInterface $em
    ): Response {
        $user = $customerRepo->find($id);
        if (!$user) {
            throw $this->createNotFoundException('User not found.');
        }

        /** @var Customer $currentUser */
        $currentUser = $this->getUser();
        if ($user->getId() === $currentUser->getId()) {
            $this->addFlash('error', 'You cannot deactivate your own admin account.');
            return $this->redirectToRoute('app_admin_users');
        }

        $token = $request->getPayload()->getString('_csrf_token');
        if (!$this->isCsrfTokenValid('toggle_active_' . $user->getId(), $token)) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('app_admin_users');
        }

        $user->setIsActive(!$user->isActive());
        $em->flush();

        $statusText = $user->isActive() ? 'activated' : 'deactivated';
        $this->addFlash('success', sprintf('User "%s" has been %s.', $user->getUsername(), $statusText));

        return $this->redirectToRoute('app_admin_users');
    }

    #[Route('/users/{id}/delete', name: 'app_admin_user_delete', methods: ['POST'])]
    public function deleteUser(
        int $id,
        Request $request,
        CustomerRepository $customerRepo,
        CartRepository $cartRepo,
        CommentRepository $commentRepo,
        EntityManagerInterface $em
    ): Response {
        $user = $customerRepo->find($id);
        if (!$user) {
            throw $this->createNotFoundException('User not found.');
        }

        /** @var Customer $currentUser */
        $currentUser = $this->getUser();
        if ($user->getId() === $currentUser->getId()) {
            $this->addFlash('error', 'You cannot delete your own admin account.');
            return $this->redirectToRoute('app_admin_users');
        }

        $token = $request->getPayload()->getString('_csrf_token');
        if (!$this->isCsrfTokenValid('delete_user_' . $user->getId(), $token)) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('app_admin_users');
        }

        // Clean up references before deleting
        foreach ($user->getProducts() as $product) {
            $product->setCreatedBy(null);
        }

        $cartItems = $cartRepo->findBy(['customer' => $user]);
        foreach ($cartItems as $item) {
            $em->remove($item);
        }

        $comments = $commentRepo->findBy(['customer' => $user]);
        foreach ($comments as $comment) {
            $em->remove($comment);
        }

        $username = $user->getUsername();
        $em->remove($user);
        $em->flush();

        $this->addFlash('success', sprintf('User "%s" has been permanently deleted.', $username));
        return $this->redirectToRoute('app_admin_users');
    }

    #[Route('/products', name: 'app_admin_products', methods: ['GET'])]
    public function products(ProductRepository $productRepo): Response
    {
        $products = $productRepo->findBy([], ['id' => 'ASC']);

        return $this->render('admin/products/index.html.twig', [
            'products' => $products,
        ]);
    }

    #[Route('/comments', name: 'app_admin_comments', methods: ['GET'])]
    public function comments(CommentRepository $commentRepo): Response
    {
        $comments = $commentRepo->findBy([], ['createdAt' => 'DESC']);

        return $this->render('admin/comments/index.html.twig', [
            'comments' => $comments,
        ]);
    }

    #[Route('/comments/{id}/toggle-visibility', name: 'app_admin_comment_toggle_visibility', methods: ['POST'])]
    public function toggleCommentVisibility(
        int $id,
        Request $request,
        CommentRepository $commentRepo,
        EntityManagerInterface $em
    ): Response {
        $comment = $commentRepo->find($id);
        if (!$comment) {
            throw $this->createNotFoundException('Comment not found.');
        }

        $token = $request->getPayload()->getString('_csrf_token');
        if (!$this->isCsrfTokenValid('toggle_comment_' . $comment->getId(), $token)) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('app_admin_comments');
        }

        $comment->setIsHidden(!$comment->isHidden());
        $em->flush();

        $statusText = $comment->isHidden() ? 'hidden from public view' : 'visible on the website';
        $this->addFlash('success', sprintf('Review #%d is now %s.', $comment->getId(), $statusText));

        return $this->redirectToRoute('app_admin_comments');
    }

    #[Route('/comments/{id}/delete', name: 'app_admin_comment_delete', methods: ['POST'])]
    public function deleteComment(
        int $id,
        Request $request,
        CommentRepository $commentRepo,
        EntityManagerInterface $em
    ): Response {
        $comment = $commentRepo->find($id);
        if (!$comment) {
            throw $this->createNotFoundException('Comment not found.');
        }

        $token = $request->getPayload()->getString('_csrf_token');
        if (!$this->isCsrfTokenValid('delete_comment_' . $comment->getId(), $token)) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('app_admin_comments');
        }

        $commentId = $comment->getId();
        $em->remove($comment);
        $em->flush();

        $this->addFlash('success', sprintf('Review #%d has been deleted permanently.', $commentId));
        return $this->redirectToRoute('app_admin_comments');
    }
}
