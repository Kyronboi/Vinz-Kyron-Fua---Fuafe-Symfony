<?php

namespace App\Controller;

use App\Entity\Cart;
use App\Entity\Customer;
use App\Repository\CartRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class CartController extends AbstractController
{
    #[Route('/cart', name: 'app_cart')]
    public function index(CartRepository $cartRepository): Response
    {
        /** @var Customer|null $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->render('order/cart.html.twig', [
                'cart_items' => [],
                'total' => 0.0,
                'not_logged_in' => true,
            ]);
        }

        $cartItems = $cartRepository->findByCustomerWithProduct($user);
        $total = 0.0;
        foreach ($cartItems as $item) {
            $total += ((float) $item->getPrice()) * $item->getQuantity();
        }

        return $this->render('order/cart.html.twig', [
            'cart_items' => $cartItems,
            'total' => $total,
            'not_logged_in' => false,
        ]);
    }

    #[Route('/cart/add', name: 'app_cart_add', methods: ['POST'])]
    public function add(Request $request, ProductRepository $productRepository, CartRepository $cartRepository, EntityManagerInterface $entityManager): Response
    {
        /** @var Customer|null $user */
        $user = $this->getUser();

        if (!$user) {
            $this->addFlash('error', 'Please sign in or create an account to add items to your cart.');
            return $this->redirectToRoute('app_login');
        }

        $productId = (int) $request->request->get('product_id');
        $sizeName = (string) $request->request->get('size', 'Regular');

        $product = $productRepository->find($productId);
        if (!$product) {
            throw $this->createNotFoundException('Product not found.');
        }

        if ($product->getStock() !== null && $product->getStock() <= 0) {
            $this->addFlash('error', sprintf('Sorry, "%s" is currently out of stock.', $product->getName()));
            return $this->redirectToRoute('app_menu');
        }

        // Determine price from matched size or product base price
        $price = (float) $product->getPrice();
        foreach ($product->getSizes() as $productSize) {
            if (strcasecmp($productSize->getSizeName(), $sizeName) === 0) {
                $price = (float) $productSize->getPrice();
                $sizeName = $productSize->getSizeName();
                break;
            }
        }

        $cartItem = $cartRepository->findOneBy([
            'customer' => $user,
            'product' => $product,
            'size' => $sizeName,
        ]);

        if ($cartItem) {
            $cartItem->setQuantity($cartItem->getQuantity() + 1);
        } else {
            $cartItem = new Cart();
            $cartItem->setCustomer($user);
            $cartItem->setProduct($product);
            $cartItem->setSize($sizeName);
            $cartItem->setQuantity(1);
            $cartItem->setPrice(number_format($price, 2, '.', ''));
            $entityManager->persist($cartItem);
        }

        $entityManager->flush();

        $this->addFlash('success', sprintf('Added "%s (%s)" to your cart.', $product->getName(), $sizeName));

        return $this->redirectToRoute('app_cart');
    }

    #[Route('/cart/remove/{id}', name: 'app_cart_remove', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function remove(int $id, CartRepository $cartRepository, EntityManagerInterface $entityManager): Response
    {
        /** @var Customer $user */
        $user = $this->getUser();

        $cartItem = $cartRepository->find($id);
        if ($cartItem && $cartItem->getCustomer() === $user) {
            $entityManager->remove($cartItem);
            $entityManager->flush();
            $this->addFlash('success', 'Item removed from your cart.');
        }

        return $this->redirectToRoute('app_cart');
    }

    #[Route('/checkout', name: 'app_checkout')]
    #[IsGranted('ROLE_USER')]
    public function checkout(CartRepository $cartRepository, EntityManagerInterface $entityManager): Response
    {
        /** @var Customer $user */
        $user = $this->getUser();

        $cartItems = $cartRepository->findByCustomerWithProduct($user);
        if (empty($cartItems)) {
            return $this->redirectToRoute('app_cart');
        }

        $total = 0.0;
        foreach ($cartItems as $item) {
            $qty = $item->getQuantity();
            $total += ((float) $item->getPrice()) * $qty;

            // Deduct stock for the purchased product
            $product = $item->getProduct();
            if ($product) {
                $currentStock = $product->getStock() ?? 0;
                $newStock = max(0, $currentStock - $qty);
                $product->setStock($newStock);
            }

            $entityManager->remove($item);
        }

        $entityManager->flush();

        return $this->render('order/checkout.html.twig', [
            'total' => $total,
            'customer' => $user,
        ]);
    }
}
