<?php

namespace App\Tests\Controller;

use App\Entity\Cart;
use App\Entity\Customer;
use App\Entity\Product;
use App\Entity\ProductSize;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class StaffProductCrudTest extends WebTestCase
{
    private function getStaffUser(string $email = 'staff@fuafe.com'): Customer
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $user = $em->getRepository(Customer::class)->findOneBy(['email' => $email]);
        $this->assertNotNull($user, "Staff user $email should exist.");
        return $user;
    }

    public function testStaffWorkspaceShowsInventory(): void
    {
        $client = static::createClient();
        $staff = $this->getStaffUser();
        $client->loginUser($staff);

        $crawler = $client->request('GET', '/staff');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Staff Portal');
        $this->assertSelectorExists('.admin-table');
        $this->assertSelectorExists('a:contains("Add New Product")');
    }

    public function testStaffCanCreateNewProduct(): void
    {
        $client = static::createClient();
        $staff = $this->getStaffUser();
        $client->loginUser($staff);

        $crawler = $client->request('GET', '/staff/products/new');
        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton('Create Product')->form([
            'name' => 'Vanilla Macchiato',
            'description' => 'A sweet blend of vanilla syrup, steamed milk, and rich espresso.',
            'image_path' => 'pictures/Asset 1.png',
            'stock' => 40,
            'small_price' => 90.00,
            'regular_price' => 105.00,
            'large_price' => 125.00,
        ]);

        $client->submit($form);
        $this->assertResponseRedirects('/staff');

        $crawler = $client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.admin-section', 'Vanilla Macchiato');
        $this->assertSelectorTextContains('.admin-section', 'was created successfully!');

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $product = $em->getRepository(Product::class)->findOneBy(['name' => 'Vanilla Macchiato']);
        $this->assertNotNull($product);
        $this->assertSame($staff->getId(), $product->getCreatedBy()?->getId());
        $this->assertSame(40, $product->getStock());
        $this->assertCount(3, $product->getSizes());
    }

    public function testStaffCanEditOwnProduct(): void
    {
        $client = static::createClient();
        $staff = $this->getStaffUser();
        $client->loginUser($staff);

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $product = $em->getRepository(Product::class)->findOneBy(['name' => 'Vanilla Macchiato']);
        $this->assertNotNull($product);

        $crawler = $client->request('GET', '/staff/products/' . $product->getId() . '/edit');
        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton('Save Changes')->form([
            'name' => 'Vanilla Macchiato Supreme',
            'description' => 'Updated blend with creamy caramel drizzle.',
            'stock' => 25,
            'regular_price' => 115.00,
        ]);

        $client->submit($form);
        $this->assertResponseRedirects('/staff');

        $client->followRedirect();
        $this->assertResponseIsSuccessful();

        $em->clear();
        $updated = $em->getRepository(Product::class)->find($product->getId());
        $this->assertSame('Vanilla Macchiato Supreme', $updated->getName());
        $this->assertSame(25, $updated->getStock());
    }

    public function testStaffCannotEditAnotherStaffsProduct(): void
    {
        $client = static::createClient();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');

        // Ensure a second staff account exists
        $hasher = static::getContainer()->get('security.user_password_hasher');
        $otherStaff = $em->getRepository(Customer::class)->findOneBy(['email' => 'barista2@fuafe.com']);
        if (!$otherStaff) {
            $otherStaff = new Customer();
            $otherStaff->setEmail('barista2@fuafe.com');
            $otherStaff->setUsername('Barista Two');
            $otherStaff->setAge(22);
            $otherStaff->setLocation('Dumaguete');
            $otherStaff->setRoles(['ROLE_STAFF']);
            $otherStaff->setIsActive(true);
            $otherStaff->setPassword($hasher->hashPassword($otherStaff, 'staff123'));
            $em->persist($otherStaff);
        }

        // Create a product owned by barista2
        $otherProduct = $em->getRepository(Product::class)->findOneBy(['name' => 'Barista2 Special Brew']);
        if (!$otherProduct) {
            $otherProduct = new Product();
            $otherProduct->setName('Barista2 Special Brew');
            $otherProduct->setDescription('Private recipe by Barista Two.');
            $otherProduct->setImagePath('pictures/Asset 2.png');
            $otherProduct->setStock(20);
            $otherProduct->setPrice('130.00');
            $otherProduct->setCreatedBy($otherStaff);
            $em->persist($otherProduct);
            $em->flush();
        }

        // Login as first staff member
        $staff = $this->getStaffUser();
        $client->loginUser($staff);

        // Attempt to edit barista2's product
        $client->catchExceptions(true);
        $client->request('GET', '/staff/products/' . $otherProduct->getId() . '/edit');

        // Must be rejected with 403 Forbidden
        $this->assertResponseStatusCodeSame(403);
    }

    public function testStaffCannotDeleteAnotherStaffsProduct(): void
    {
        $client = static::createClient();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $otherProduct = $em->getRepository(Product::class)->findOneBy(['name' => 'Barista2 Special Brew']);
        $this->assertNotNull($otherProduct);

        // Login as first staff member
        $staff = $this->getStaffUser();
        $client->loginUser($staff);

        // Attempt to delete barista2's product
        $client->catchExceptions(true);
        $client->request('POST', '/staff/products/' . $otherProduct->getId() . '/delete', [
            '_csrf_token' => 'dummy_token',
        ]);

        // Must be rejected with 403 Forbidden
        $this->assertResponseStatusCodeSame(403);

        // Verify product was NOT deleted
        $em->clear();
        $stillExists = $em->getRepository(Product::class)->find($otherProduct->getId());
        $this->assertNotNull($stillExists);
    }

    public function testStaffCanDeleteOwnProductAndCleansCartItems(): void
    {
        $client = static::createClient();
        $staff = $this->getStaffUser();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $product = $em->getRepository(Product::class)->findOneBy(['name' => 'Vanilla Macchiato Supreme']);
        $this->assertNotNull($product);

        // Add a cart item pointing to this product to test cascade safety
        $customer = $em->getRepository(Customer::class)->findOneBy(['email' => 'customer@fuafe.com']);
        $cartItem = new Cart();
        $cartItem->setCustomer($customer);
        $cartItem->setProduct($product);
        $cartItem->setSize('Regular');
        $cartItem->setQuantity(1);
        $cartItem->setPrice($product->getPrice());
        $em->persist($cartItem);
        $em->flush();

        $client->loginUser($staff);

        // Go to staff page to get valid CSRF token from the delete form
        $crawler = $client->request('GET', '/staff');
        $deleteForm = $crawler->filter('form[action="/staff/products/' . $product->getId() . '/delete"]');
        $this->assertCount(1, $deleteForm);
        $csrfToken = $deleteForm->filter('input[name="_csrf_token"]')->attr('value');

        // Submit deletion
        $client->request('POST', '/staff/products/' . $product->getId() . '/delete', [
            '_csrf_token' => $csrfToken,
        ]);

        $this->assertResponseRedirects('/staff');
        $client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.admin-section', 'was deleted successfully!');

        $em->clear();
        $deleted = $em->getRepository(Product::class)->find($product->getId());
        $this->assertNull($deleted);
    }
}
