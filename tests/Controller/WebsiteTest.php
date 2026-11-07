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
}
