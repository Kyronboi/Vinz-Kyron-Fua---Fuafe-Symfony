<?php

namespace App\Controller;

use App\Entity\Customer;
use App\Entity\Product;
use App\Entity\ProductSize;
use App\Repository\CartRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_STAFF')]
#[Route('/staff')]
final class StaffController extends AbstractController
{
    #[Route('', name: 'app_staff', methods: ['GET'])]
    public function index(ProductRepository $productRepo): Response
    {
        /** @var Customer $user */
        $user = $this->getUser();
        $products = $productRepo->findBy([], ['id' => 'ASC']);

        $totalCount = count($products);
        $myCount = 0;
        $lowStockCount = 0;

        foreach ($products as $p) {
            if ($p->getCreatedBy()?->getId() === $user->getId()) {
                $myCount++;
            }
            if ($p->getStock() !== null && $p->getStock() < 15) {
                $lowStockCount++;
            }
        }

        return $this->render('staff/index.html.twig', [
            'products' => $products,
            'totalCount' => $totalCount,
            'myCount' => $myCount,
            'lowStockCount' => $lowStockCount,
        ]);
    }

    #[Route('/products/new', name: 'app_staff_product_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        /** @var Customer $user */
        $user = $this->getUser();

        if ($request->isMethod('POST')) {
            $payload = $request->getPayload();
            $token = $payload->getString('_csrf_token');

            if (!$this->isCsrfTokenValid('staff_product_new', $token)) {
                $this->addFlash('error', 'Invalid security token. Please try again.');
                return $this->redirectToRoute('app_staff_product_new');
            }

            $name = trim($payload->getString('name'));
            $description = trim($payload->getString('description'));
            $imagePath = trim($payload->getString('image_path')) ?: 'pictures/Asset 1.png';
            $stock = $payload->getInt('stock', 50);
            $smallPrice = number_format($payload->get('small_price', 85.00), 2, '.', '');
            $regularPrice = number_format($payload->get('regular_price', 95.00), 2, '.', '');
            $largePrice = number_format($payload->get('large_price', 110.00), 2, '.', '');

            if ($name === '' || $description === '') {
                $this->addFlash('error', 'Product name and description are required.');
                return $this->render('staff/new.html.twig', [
                    'formData' => $payload->all(),
                ]);
            }

            if ($stock < 0) {
                $this->addFlash('error', 'Stock count cannot be negative.');
                return $this->render('staff/new.html.twig', [
                    'formData' => $payload->all(),
                ]);
            }

            $product = new Product();
            $product->setName($name);
            $product->setDescription($description);
            $product->setImagePath($imagePath);
            $product->setStock($stock);
            $product->setPrice($regularPrice);
            $product->setCreatedBy($user);

            // Create standard size variations
            $sizeSmall = new ProductSize();
            $sizeSmall->setSizeName('Small');
            $sizeSmall->setPrice($smallPrice);
            $product->addSize($sizeSmall);
            $em->persist($sizeSmall);

            $sizeRegular = new ProductSize();
            $sizeRegular->setSizeName('Regular');
            $sizeRegular->setPrice($regularPrice);
            $product->addSize($sizeRegular);
            $em->persist($sizeRegular);

            $sizeLarge = new ProductSize();
            $sizeLarge->setSizeName('Large');
            $sizeLarge->setPrice($largePrice);
            $product->addSize($sizeLarge);
            $em->persist($sizeLarge);

            $em->persist($product);
            $em->flush();

            $this->addFlash('success', sprintf('Product "%s" was created successfully!', $product->getName()));
            return $this->redirectToRoute('app_staff');
        }

        return $this->render('staff/new.html.twig', [
            'formData' => [],
        ]);
    }

    #[Route('/products/{id}/edit', name: 'app_staff_product_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, ProductRepository $productRepo, EntityManagerInterface $em): Response
    {
        $product = $productRepo->find($id);
        if (!$product) {
            throw $this->createNotFoundException('Product not found.');
        }

        /** @var Customer $user */
        $user = $this->getUser();

        // Ownership restriction: staff can only edit what they created (Admins can edit any)
        if ($product->getCreatedBy()?->getId() !== $user->getId() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Access denied: You can only edit products you created.');
        }

        if ($request->isMethod('POST')) {
            $payload = $request->getPayload();
            $token = $payload->getString('_csrf_token');

            if (!$this->isCsrfTokenValid('staff_product_edit_' . $product->getId(), $token)) {
                $this->addFlash('error', 'Invalid security token. Please try again.');
                return $this->redirectToRoute('app_staff_product_edit', ['id' => $product->getId()]);
            }

            $name = trim($payload->getString('name'));
            $description = trim($payload->getString('description'));
            $imagePath = trim($payload->getString('image_path')) ?: $product->getImagePath();
            $stock = $payload->getInt('stock', $product->getStock() ?? 0);
            $smallPrice = number_format($payload->get('small_price', 0), 2, '.', '');
            $regularPrice = number_format($payload->get('regular_price', 0), 2, '.', '');
            $largePrice = number_format($payload->get('large_price', 0), 2, '.', '');

            if ($name === '' || $description === '') {
                $this->addFlash('error', 'Product name and description cannot be blank.');
                return $this->redirectToRoute('app_staff_product_edit', ['id' => $product->getId()]);
            }

            $product->setName($name);
            $product->setDescription($description);
            $product->setImagePath($imagePath);
            $product->setStock($stock);
            $product->setPrice($regularPrice);

            // Update size prices
            $sizeMap = [
                'Small' => $smallPrice,
                'Regular' => $regularPrice,
                'Large' => $largePrice,
            ];

            $existingSizes = [];
            foreach ($product->getSizes() as $size) {
                $existingSizes[$size->getSizeName()] = $size;
                if (isset($sizeMap[$size->getSizeName()]) && (float)$sizeMap[$size->getSizeName()] > 0) {
                    $size->setPrice($sizeMap[$size->getSizeName()]);
                }
            }

            // If a size was missing, create it
            foreach ($sizeMap as $sizeName => $price) {
                if (!isset($existingSizes[$sizeName]) && (float)$price > 0) {
                    $newSize = new ProductSize();
                    $newSize->setSizeName($sizeName);
                    $newSize->setPrice($price);
                    $product->addSize($newSize);
                    $em->persist($newSize);
                }
            }

            $em->flush();

            $this->addFlash('success', sprintf('Product "%s" was updated successfully!', $product->getName()));
            return $this->redirectToRoute('app_staff');
        }

        // Map existing size prices for the form
        $prices = ['Small' => '', 'Regular' => $product->getPrice(), 'Large' => ''];
        foreach ($product->getSizes() as $size) {
            $prices[$size->getSizeName()] = $size->getPrice();
        }

        return $this->render('staff/edit.html.twig', [
            'product' => $product,
            'prices' => $prices,
        ]);
    }

    #[Route('/products/{id}/delete', name: 'app_staff_product_delete', methods: ['POST'])]
    public function delete(
        int $id,
        Request $request,
        ProductRepository $productRepo,
        CartRepository $cartRepo,
        EntityManagerInterface $em
    ): Response {
        $product = $productRepo->find($id);
        if (!$product) {
            throw $this->createNotFoundException('Product not found.');
        }

        /** @var Customer $user */
        $user = $this->getUser();

        // Ownership restriction: staff can only delete what they created (Admins can delete any)
        if ($product->getCreatedBy()?->getId() !== $user->getId() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Access denied: You can only delete products you created.');
        }

        $token = $request->getPayload()->getString('_csrf_token');
        if (!$this->isCsrfTokenValid('delete_product_' . $product->getId(), $token)) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('app_staff');
        }

        // Remove cart entries referencing this product first to maintain database integrity
        $cartItems = $cartRepo->findBy(['product' => $product]);
        foreach ($cartItems as $item) {
            $em->remove($item);
        }

        $productName = $product->getName();
        $em->remove($product);
        $em->flush();

        $this->addFlash('success', sprintf('Product "%s" was deleted successfully!', $productName));
        return $this->redirectToRoute('app_staff');
    }
}
