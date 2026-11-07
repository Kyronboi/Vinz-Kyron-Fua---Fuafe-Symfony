<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

class ProductService
{
    private string $filePath;

    /**
     * @var array<int, array{id: int, name: string, description: string, price: float, image_path: string, stock: int, sizes: array<int, array{name: string, price: float}>}>
     */
    private array $defaultProducts = [
        1 => [
            'id' => 1,
            'name' => 'Espresso',
            'description' => 'Strong, bold, and pure. A classic shot of espresso brewed to perfection.',
            'price' => 95.00,
            'image_path' => 'pictures/Asset 1.png',
            'stock' => 50,
            'sizes' => [
                ['name' => 'Small', 'price' => 85.00],
                ['name' => 'Regular', 'price' => 95.00],
                ['name' => 'Large', 'price' => 110.00],
            ],
        ],
        2 => [
            'id' => 2,
            'name' => 'Mocha',
            'description' => 'Rich chocolate combined with our signature espresso and steamed velvety milk.',
            'price' => 120.00,
            'image_path' => 'pictures/Asset 2.png',
            'stock' => 50,
            'sizes' => [
                ['name' => 'Small', 'price' => 105.00],
                ['name' => 'Regular', 'price' => 120.00],
                ['name' => 'Large', 'price' => 135.00],
            ],
        ],
        3 => [
            'id' => 3,
            'name' => 'Latte',
            'description' => 'Smooth milk with a delicate layer of foam and fragrant espresso.',
            'price' => 110.00,
            'image_path' => 'pictures/Asset 3.png',
            'stock' => 50,
            'sizes' => [
                ['name' => 'Small', 'price' => 95.00],
                ['name' => 'Regular', 'price' => 110.00],
                ['name' => 'Large', 'price' => 125.00],
            ],
        ],
        4 => [
            'id' => 4,
            'name' => 'Cappuccino',
            'description' => 'The perfect balance of espresso, silky steam, and rich micro-foam.',
            'price' => 115.00,
            'image_path' => 'pictures/cappuccino.jpg',
            'stock' => 50,
            'sizes' => [
                ['name' => 'Small', 'price' => 100.00],
                ['name' => 'Regular', 'price' => 115.00],
                ['name' => 'Large', 'price' => 130.00],
            ],
        ],
    ];

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        string $projectDir,
    ) {
        $this->filePath = $projectDir . '/var/products.json';
    }

    /**
     * @return array<int, array{id: int, name: string, description: string, price: float, image_path: string, stock: int, sizes: array<int, array{name: string, price: float}>}>
     */
    public function getAll(): array
    {
        if (file_exists($this->filePath)) {
            $data = json_decode((string)file_get_contents($this->filePath), true);
            if (is_array($data) && !empty($data)) {
                $products = [];
                foreach ($data as $item) {
                    if (isset($item['id'])) {
                        $products[(int)$item['id']] = $item;
                    }
                }
                return $products;
            }
        }

        // Seed with defaults
        $this->save($this->defaultProducts);
        return $this->defaultProducts;
    }

    public function getById(int $id): ?array
    {
        $products = $this->getAll();
        return $products[$id] ?? null;
    }

    /**
     * @return array<int, array<string, float>>
     */
    public function getSizesLookup(): array
    {
        $lookup = [];
        foreach ($this->getAll() as $product) {
            $id = $product['id'];
            $lookup[$id] = [];
            foreach ($product['sizes'] ?? [] as $size) {
                $lookup[$id][$size['name']] = (float)$size['price'];
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
        $products = $this->getAll();
        $nextId = !empty($products) ? max(array_keys($products)) + 1 : 1;

        $products[$nextId] = [
            'id' => $nextId,
            'name' => $name,
            'description' => $description,
            'price' => $regularPrice,
            'image_path' => $imagePath,
            'stock' => max(0, $stock),
            'sizes' => [
                ['name' => 'Small', 'price' => $smallPrice],
                ['name' => 'Regular', 'price' => $regularPrice],
                ['name' => 'Large', 'price' => $largePrice],
            ],
        ];

        $this->save($products);
        return $nextId;
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
        $products = $this->getAll();
        if (!isset($products[$id])) {
            return false;
        }

        $products[$id]['name'] = $name !== '' ? $name : $products[$id]['name'];
        $products[$id]['description'] = $description !== '' ? $description : $products[$id]['description'];
        $products[$id]['image_path'] = $imagePath !== '' ? $imagePath : $products[$id]['image_path'];
        $products[$id]['stock'] = max(0, $stock);
        $products[$id]['price'] = $regularPrice > 0 ? $regularPrice : $products[$id]['price'];
        $products[$id]['sizes'] = [
            ['name' => 'Small', 'price' => $smallPrice > 0 ? $smallPrice : ($products[$id]['sizes'][0]['price'] ?? $products[$id]['price'])],
            ['name' => 'Regular', 'price' => $regularPrice > 0 ? $regularPrice : $products[$id]['price']],
            ['name' => 'Large', 'price' => $largePrice > 0 ? $largePrice : ($products[$id]['sizes'][2]['price'] ?? $products[$id]['price'])],
        ];

        $this->save($products);
        return true;
    }

    public function deleteProduct(int $id): bool
    {
        $products = $this->getAll();
        if (isset($products[$id])) {
            unset($products[$id]);
            $this->save($products);
            return true;
        }
        return false;
    }

    /**
     * @param array<int, array<string, mixed>> $products
     */
    private function save(array $products): void
    {
        $dir = dirname($this->filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($this->filePath, json_encode($products, JSON_PRETTY_PRINT));
    }
}
