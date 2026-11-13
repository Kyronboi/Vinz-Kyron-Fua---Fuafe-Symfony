<?php

namespace App\Controller;

use App\Repository\CommentRepository;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(ProductRepository $productRepository, CommentRepository $commentRepository): Response
    {
        $products = $productRepository->findAll();
        $comments = $commentRepository->findLatestVisible(6);

        return $this->render('home/index.html.twig', [
            'products' => $products,
            'comments' => $comments,
        ]);
    }
}
