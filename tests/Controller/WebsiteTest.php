<?php

namespace App\Tests\Controller;

use App\Entity\Customer;
use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class WebsiteTest extends WebTestCase
{
    public function testHomePageIsSuccessful(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h2.section-title', 'What is Fuafe?');
        $this->assertSelectorExists('.carousel-track');
    }

    public function testMenuPageIsSuccessfulAndContainsProducts(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/menu');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1.page-title', 'Our Coffee Menu');
        $this->assertSelectorExists('.menu-card-lg');
    }

    public function testProductDetailPage(): void
    {
        $client = static::createClient();
        $client->request('GET', '/product/1');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.product-detail-card');
        $this->assertSelectorExists('.size-selection');
    }

    public function testCommentsPageIsSuccessful(): void
    {
        $client = static::createClient();
        $client->request('GET', '/comments');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1.page-title', 'Customer Comments');
        $this->assertSelectorExists('.comment-box');
    }

    public function testLoginPageIsSuccessful(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.auth-card h2', 'Welcome Back');
        $this->assertSelectorExists('button[type="submit"]');
    }

    public function testRegisterPageIsSuccessful(): void
    {
        $client = static::createClient();
        $client->request('GET', '/register');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.auth-card h2', 'Create Account');
        $this->assertSelectorExists('form[name="registration_form"]');
    }

    public function testCartPageIsSuccessful(): void
    {
        $client = static::createClient();
        $client->request('GET', '/cart');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1.page-title', 'Your Cart');
    }

    public function testAuthenticatedUserCartAndCommentWorkflow(): void
    {
        $client = static::createClient();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $customerRepo = $em->getRepository(Customer::class);
        $productRepo = $em->getRepository(Product::class);

        $customer = $customerRepo->findOneBy(['email' => 'customer@fuafe.com']);
        $this->assertNotNull($customer, 'Default customer should exist from seeder');

        $product = $productRepo->findOneBy(['name' => 'Espresso']);
        $this->assertNotNull($product, 'Espresso product should exist from seeder');

        // Login as customer
        $client->loginUser($customer);

        // Add to cart
        $client->request('POST', '/cart/add', [
            'product_id' => $product->getId(),
            'size' => 'Regular',
        ]);
        $this->assertResponseRedirects('/cart');

        $crawler = $client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.cart-table', 'Espresso');

        // Post a review
        $client->request('GET', '/comments/add');
        $this->assertResponseIsSuccessful();

        $client->request('POST', '/comments/add', [
            'rating' => 5,
            'comment' => 'Automated test comment - love the coffee!',
        ]);
        $this->assertResponseRedirects('/comments');

        $client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.comments-grid', 'Automated test comment - love the coffee!');

        // Checkout
        $client->request('GET', '/checkout');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.checkout-card h2', 'Order Placed Successfully!');
    }

    public function testAdminAndStaffAccountsAndProductOwnership(): void
    {
        $client = static::createClient();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $customerRepo = $em->getRepository(Customer::class);
        $productRepo = $em->getRepository(Product::class);

        $admin = $customerRepo->findOneBy(['email' => 'admin@fuafe.com']);
        $this->assertNotNull($admin, 'Admin account should exist');
        $this->assertContains('ROLE_ADMIN', $admin->getRoles());
        $this->assertContains('ROLE_USER', $admin->getRoles());
        $this->assertTrue($admin->isActive());
        $this->assertNotNull($admin->getCreatedAt());

        $staff = $customerRepo->findOneBy(['email' => 'staff@fuafe.com']);
        $this->assertNotNull($staff, 'Staff account should exist');
        $this->assertContains('ROLE_STAFF', $staff->getRoles());
        $this->assertContains('ROLE_USER', $staff->getRoles());
        $this->assertTrue($staff->isActive());
        $this->assertNotNull($staff->getCreatedAt());

        $products = $productRepo->findAll();
        $this->assertNotEmpty($products);
        foreach ($products as $product) {
            $this->assertNotNull($product->getCreatedBy(), 'Product ' . $product->getName() . ' should have an owner');
        }

        // Test login as admin
        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Log In')->form([
            'email' => 'admin@fuafe.com',
            'password' => 'admin123',
        ]);
        $client->submit($form);
        $this->assertResponseRedirects('/');

        // Restart client session to clear auth for staff test
        $client->restart();

        // Test login as staff
        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Log In')->form([
            'email' => 'staff@fuafe.com',
            'password' => 'staff123',
        ]);
        $client->submit($form);
        $this->assertResponseRedirects('/');
    }

    public function testCheckoutDeductsProductStockAndShowsBackToMenuButton(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        // Find or create customer
        $customer = $em->getRepository(Customer::class)->findOneBy(['email' => 'customer@fuafe.com']);
        $this->assertNotNull($customer);

        // Find product
        $product = $em->getRepository(Product::class)->findOneBy([]);
        $this->assertNotNull($product);

        $initialStock = $product->getStock() ?? 30;
        $product->setStock($initialStock);
        $em->flush();

        // Login as customer
        $client->loginUser($customer);

        // Add 2 items of product to cart
        $client->request('POST', '/cart/add', [
            'product_id' => $product->getId(),
            'size' => 'Regular',
        ]);
        $this->assertResponseRedirects('/cart');
        $client->followRedirect();

        // Verify cart page has "Back to Menu" button
        $this->assertSelectorExists('a[href="/menu"]');

        // Add a second quantity
        $client->request('POST', '/cart/add', [
            'product_id' => $product->getId(),
            'size' => 'Regular',
        ]);
        $this->assertResponseRedirects('/cart');

        // Perform checkout
        $crawler = $client->request('GET', '/checkout');
        $this->assertResponseIsSuccessful();

        // Verify checkout confirmation contains Back to Menu button
        $this->assertSelectorExists('.checkout-btn');
        $this->assertSelectorTextContains('.checkout-btn', 'Back to Menu');

        // Clear EM and re-fetch product to verify stock deduction
        $em->clear();
        $refreshedProduct = $em->getRepository(Product::class)->find($product->getId());
        $this->assertNotNull($refreshedProduct);
        $this->assertSame($initialStock - 2, $refreshedProduct->getStock(), 'Stock must be decremented by quantity purchased');
    }
}
