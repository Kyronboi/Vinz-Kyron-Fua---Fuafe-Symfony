<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Customer;
use App\Repository\CommentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class CommentController extends AbstractController
{
    #[Route('/comments', name: 'app_comments')]
    public function index(CommentRepository $commentRepository): Response
    {
        $comments = $commentRepository->findBy(['isHidden' => false], ['createdAt' => 'DESC']);

        return $this->render('comments/index.html.twig', [
            'comments' => $comments,
        ]);
    }

    #[Route('/comments/add', name: 'app_comment_add', methods: ['GET', 'POST'])]
    public function add(Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var Customer|null $user */
        $user = $this->getUser();

        if (!$user) {
            $this->addFlash('error', 'Please sign in to leave a review.');
            return $this->redirectToRoute('app_login');
        }

        if ($request->isMethod('POST')) {
            $rating = (int) $request->request->get('rating', 5);
            $commentText = trim((string) $request->request->get('comment'));

            if ($rating < 1 || $rating > 5) {
                $rating = 5;
            }

            if ($commentText === '') {
                $this->addFlash('error', 'Please write a review before submitting.');
            } else {
                $comment = new Comment();
                $comment->setCustomer($user);
                $comment->setRating($rating);
                $comment->setComment($commentText);
                $comment->setIsHidden(false);
                $comment->setCreatedAt(new \DateTimeImmutable());

                $entityManager->persist($comment);
                $entityManager->flush();

                $this->addFlash('success', 'Thank you! Your review has been posted.');
                return $this->redirectToRoute('app_comments');
            }
        }

        return $this->render('comments/add.html.twig');
    }
}
