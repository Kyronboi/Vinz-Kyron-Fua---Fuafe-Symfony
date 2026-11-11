<?php

namespace App\Command;

use App\Entity\Cart;
use App\Entity\Comment;
use App\Entity\Customer;
use App\Entity\Product;
use App\Entity\ProductSize;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:seed-database',
    description: 'Seeds the database with initial products, admin/staff/customer accounts, and customer comments',
)]
class DatabaseSeedCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('fresh', null, InputOption::VALUE_NONE, 'Clear existing tables and reseed from scratch');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $customerRepo = $this->entityManager->getRepository(Customer::class);
        $productRepo = $this->entityManager->getRepository(Product::class);

        if ($input->getOption('fresh')) {
            $io->warning('Clearing existing database tables...');
            $connection = $this->entityManager->getConnection();
            $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
            $connection->executeStatement('TRUNCATE TABLE cart');
            $connection->executeStatement('TRUNCATE TABLE comment');
            $connection->executeStatement('TRUNCATE TABLE product_size');
            $connection->executeStatement('TRUNCATE TABLE product');
            $connection->executeStatement('TRUNCATE TABLE customer');
            $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
            $io->success('Database tables cleared.');
        }

        // 1. Seed Staff Account first so we can link products to them
        $staff = $customerRepo->findOneBy(['email' => 'staff@fuafe.com']);
        if (!$staff) {
            $staff = new Customer();
            $staff->setEmail('staff@fuafe.com');
            $staff->setUsername('Fuafe Staff');
            $staff->setAge(26);
            $staff->setLocation('Dumaguete City Branch');
            $staff->setRoles(['ROLE_STAFF']);
            $staff->setIsActive(true);
            $staff->setCreatedAt(new \DateTimeImmutable('2026-08-15 08:00:00'));
            $staff->setPassword($this->passwordHasher->hashPassword($staff, 'staff123'));
            $this->entityManager->persist($staff);
            $io->success('Created staff account: staff@fuafe.com / staff123');
        } else {
            $io->info('Staff account already exists.');
        }

        // 2. Seed Admin Account
        $admin = $customerRepo->findOneBy(['email' => 'admin@fuafe.com']);
        if (!$admin) {
            $admin = new Customer();
            $admin->setEmail('admin@fuafe.com');
            $admin->setUsername('Fuafe Admin');
            $admin->setAge(30);
            $admin->setLocation('Dumaguete City (HQ)');
            $admin->setRoles(['ROLE_ADMIN']);
            $admin->setIsActive(true);
            $admin->setCreatedAt(new \DateTimeImmutable('2026-08-01 08:00:00'));
            $admin->setPassword($this->passwordHasher->hashPassword($admin, 'admin123'));
            $this->entityManager->persist($admin);
            $io->success('Created admin account: admin@fuafe.com / admin123');
        } else {
            $io->info('Admin account already exists.');
        }

        // 3. Seed Customers and Comments
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
            $customer = $customerRepo->findOneBy(['email' => $data['email']]);
            if (!$customer) {
                $customer = new Customer();
                $customer->setEmail($data['email']);
                $customer->setUsername($data['username']);
                $customer->setAge($data['age']);
                $customer->setLocation($data['location']);
                $customer->setRoles(['ROLE_USER']);
                $customer->setIsActive(true);
                $customer->setCreatedAt(new \DateTimeImmutable('2026-08-20 10:00:00'));
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
        }
        $this->entityManager->flush();

        // 4. Seed Products if not already seeded
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
                $product->setCreatedBy($staff);

                foreach ($data['sizes'] as $sizeData) {
                    $size = new ProductSize();
                    $size->setSizeName($sizeData['name']);
                    $size->setPrice($sizeData['price']);
                    $product->addSize($size);
                    $this->entityManager->persist($size);
                }

                $this->entityManager->persist($product);
            }
            $io->success('Seeded initial products, sizes, and linked to staff.');
        } else {
            // Assign existing products without owner to staff
            $products = $productRepo->findAll();
            $assigned = 0;
            foreach ($products as $p) {
                if ($p->getCreatedBy() === null) {
                    $p->setCreatedBy($staff);
                    $assigned++;
                }
            }
            if ($assigned > 0) {
                $io->info(sprintf('Assigned %d existing products to staff account.', $assigned));
            } else {
                $io->info('Products already exist and have owners.');
            }
        }

        $this->entityManager->flush();

        $io->success('Database seeding completed successfully.');
        return Command::SUCCESS;
    }
}
