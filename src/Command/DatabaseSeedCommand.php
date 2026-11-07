<?php

namespace App\Command;

use App\Entity\Comment;
use App\Entity\Customer;
use App\Entity\Product;
use App\Entity\ProductSize;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:seed-database',
    description: 'Seeds the database with initial products, customers, and customer comments',
)]
class DatabaseSeedCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $productRepo = $this->entityManager->getRepository(Product::class);
        $customerRepo = $this->entityManager->getRepository(Customer::class);

        // 1. Seed Products if not already seeded
        if ($productRepo->count([]) === 0) {
            $productsData = [
                [
                    'name' => 'Espresso',
                    'description' => 'Strong, bold, and pure. A classic shot of espresso brewed to perfection.',
                    'price' => '95.00',
                    'imagePath' => 'pictures/Asset 1.png',
                    'stock' => 50,
                    'sizes' => [
                        ['name' => 'Small', 'price' => '85.00'],
                        ['name' => 'Regular', 'price' => '95.00'],
                        ['name' => 'Large', 'price' => '110.00'],
                    ],
                ],
                [
                    'name' => 'Mocha',
                    'description' => 'Rich chocolate combined with our signature espresso and steamed velvety milk.',
                    'price' => '120.00',
                    'imagePath' => 'pictures/Asset 2.png',
                    'stock' => 50,
                    'sizes' => [
                        ['name' => 'Small', 'price' => '105.00'],
                        ['name' => 'Regular', 'price' => '120.00'],
                        ['name' => 'Large', 'price' => '135.00'],
                    ],
                ],
                [
                    'name' => 'Latte',
                    'description' => 'Smooth milk with a delicate layer of foam and fragrant espresso.',
                    'price' => '110.00',
                    'imagePath' => 'pictures/Asset 3.png',
                    'stock' => 50,
                    'sizes' => [
                        ['name' => 'Small', 'price' => '95.00'],
                        ['name' => 'Regular', 'price' => '110.00'],
                        ['name' => 'Large', 'price' => '125.00'],
                    ],
                ],
                [
                    'name' => 'Cappuccino',
                    'description' => 'The perfect balance of espresso, silky steam, and rich micro-foam.',
                    'price' => '115.00',
                    'imagePath' => 'pictures/cappuccino.jpg',
                    'stock' => 50,
                    'sizes' => [
                        ['name' => 'Small', 'price' => '100.00'],
                        ['name' => 'Regular', 'price' => '115.00'],
                        ['name' => 'Large', 'price' => '130.00'],
                    ],
                ],
            ];

            foreach ($productsData as $data) {
                $product = new Product();
                $product->setName($data['name']);
                $product->setDescription($data['description']);
                $product->setPrice($data['price']);
                $product->setImagePath($data['imagePath']);
                $product->setStock($data['stock']);

                foreach ($data['sizes'] as $sizeData) {
                    $size = new ProductSize();
                    $size->setSizeName($sizeData['name']);
                    $size->setPrice($sizeData['price']);
                    $product->addSize($size);
                    $this->entityManager->persist($size);
                }

                $this->entityManager->persist($product);
            }
            $io->success('Seeded initial products and sizes.');
        } else {
            $io->info('Products already exist in database.');
        }

        // 2. Seed Customers and Comments
        if ($customerRepo->count([]) === 0) {
            $customersData = [
                [
                    'email' => 'maria@example.com',
                    'username' => 'Maria Santos',
                    'age' => 24,
                    'location' => 'Bajumpandan, Dumaguete',
                    'comment' => [
                        'rating' => 5,
                        'text' => 'The iced latte is absolutely the best in Dumaguete! Fast delivery and always served cold and fresh.',
                        'createdAt' => new \DateTimeImmutable('2026-09-01 10:00:00'),
                    ],
                ],
                [
                    'email' => 'john@example.com',
                    'username' => 'John Doe',
                    'age' => 28,
                    'location' => 'Daro, Dumaguete',
                    'comment' => [
                        'rating' => 5,
                        'text' => 'Their Espresso gives the exact kick I need to start my workday. Smooth texture and amazing aroma!',
                        'createdAt' => new \DateTimeImmutable('2026-09-03 14:30:00'),
                    ],
                ],
                [
                    'email' => 'alex@example.com',
                    'username' => 'Alex Rivera',
                    'age' => 22,
                    'location' => 'Piapi, Dumaguete',
                    'comment' => [
                        'rating' => 4,
                        'text' => 'Rich chocolate combined with bold coffee. The Mocha is definitely my new go-to favorite.',
                        'createdAt' => new \DateTimeImmutable('2026-09-05 09:15:00'),
                    ],
                ],
                [
                    'email' => 'sarah@example.com',
                    'username' => 'Sarah Lim',
                    'age' => 26,
                    'location' => 'Banilad, Dumaguete',
                    'comment' => [
                        'rating' => 5,
                        'text' => 'Fast delivery and very friendly staff. The packaging kept everything neatly intact.',
                        'createdAt' => new \DateTimeImmutable('2026-09-08 16:45:00'),
                    ],
                ],
                [
                    'email' => 'customer@fuafe.com',
                    'username' => 'Fuafe Customer',
                    'age' => 25,
                    'location' => 'Dumaguete City',
                    'comment' => null,
                ],
            ];

            foreach ($customersData as $data) {
                $customer = new Customer();
                $customer->setEmail($data['email']);
                $customer->setUsername($data['username']);
                $customer->setAge($data['age']);
                $customer->setLocation($data['location']);
                $customer->setRoles(['ROLE_USER']);
                $customer->setPassword($this->passwordHasher->hashPassword($customer, 'password123'));
                $this->entityManager->persist($customer);

                if ($data['comment']) {
                    $comment = new Comment();
                    $comment->setCustomer($customer);
                    $comment->setRating($data['comment']['rating']);
                    $comment->setComment($data['comment']['text']);
                    $comment->setIsHidden(false);
                    $comment->setCreatedAt($data['comment']['createdAt']);
                    $this->entityManager->persist($comment);
                }
            }
            $io->success('Seeded initial customers and comments.');
        } else {
            $io->info('Customers already exist in database.');
        }

        $this->entityManager->flush();

        $io->success('Database seeding completed successfully.');
        return Command::SUCCESS;
    }
}
