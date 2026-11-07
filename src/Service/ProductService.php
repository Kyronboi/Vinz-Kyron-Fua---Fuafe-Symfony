<?php

namespace App\Service;

use App\Entity\Product;
use App\Entity\ProductSize;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;

class ProductService
{
    public function __construct(
        private ProductRepository $productRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return Product[]
     */
    public function getAll(): array
    {
        return $this->productRepository->findAll();
    }

    public function getById(int $id): ?Product
    {
        return $this->productRepository->find($id);
    }

    /**
     * @return array<int, array<string, float>>
     */
    public function getSizesLookup(): array
    {
        $lookup = [];
        foreach ($this->getAll() as $product) {
            $id = $product->getId();
            $lookup[$id] = [];
            foreach ($product->getSizes() as $size) {
                $lookup[$id][$size->getSizeName()] = (float) $size->getPrice();
            }
        }
        return $lookup;
    }

    public function addProduct(
        string $name,
        string $description,
        string $imagePath,
        int $stock,
        float $smallPrice,
        float $regularPrice,
        float $largePrice
    ): int {
        $product = new Product();
        $product->setName($name);
        $product->setDescription($description);
        $product->setPrice((string) $regularPrice);
        $product->setImagePath($imagePath);
        $product->setStock(max(0, $stock));

        $small = (new ProductSize())->setSizeName('Small')->setPrice((string) $smallPrice);
        $regular = (new ProductSize())->setSizeName('Regular')->setPrice((string) $regularPrice);
        $large = (new ProductSize())->setSizeName('Large')->setPrice((string) $largePrice);

        $product->addSize($small);
        $product->addSize($regular);
        $product->addSize($large);

        $this->entityManager->persist($product);
        $this->entityManager->persist($small);
        $this->entityManager->persist($regular);
        $this->entityManager->persist($large);
        $this->entityManager->flush();

        return (int) $product->getId();
    }

    public function updateProduct(
        int $id,
        string $name,
        string $description,
        string $imagePath,
        int $stock,
        float $smallPrice,
        float $regularPrice,
        float $largePrice
    ): bool {
        $product = $this->getById($id);
        if (!$product) {
            return false;
        }

        if ($name !== '') {
            $product->setName($name);
        }
        if ($description !== '') {
            $product->setDescription($description);
        }
        if ($imagePath !== '') {
            $product->setImagePath($imagePath);
        }
        $product->setStock(max(0, $stock));
        if ($regularPrice > 0) {
            $product->setPrice((string) $regularPrice);
        }

        foreach ($product->getSizes() as $size) {
            if ($size->getSizeName() === 'Small' && $smallPrice > 0) {
                $size->setPrice((string) $smallPrice);
            } elseif ($size->getSizeName() === 'Regular' && $regularPrice > 0) {
                $size->setPrice((string) $regularPrice);
            } elseif ($size->getSizeName() === 'Large' && $largePrice > 0) {
                $size->setPrice((string) $largePrice);
            }
        }

        $this->entityManager->flush();
        return true;
    }

    public function deleteProduct(int $id): bool
    {
        $product = $this->getById($id);
        if (!$product) {
            return false;
        }

        $this->entityManager->remove($product);
        $this->entityManager->flush();
        return true;
    }
}
