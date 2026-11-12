<?php

namespace App\Tests\Controller;

use App\Entity\Customer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AccessControlTest extends WebTestCase
{
    private function getCustomerByEmail(string $email): Customer
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $customer = $em->getRepository(Customer::class)->findOneBy(['email' => $email]);
        $this->assertNotNull($customer, "Account $email should exist in database.");
        return $customer;
    }

    public function testAnonymousAccessToAdminRedirectsToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin');

        $this->assertResponseRedirects('/login');
    }

    public function testAnonymousAccessToStaffRedirectsToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/staff');

        $this->assertResponseRedirects('/login');
    }

    public function testCustomerAccessToAdminIsForbidden(): void
    {
        $client = static::createClient();
        $customer = $this->getCustomerByEmail('customer@fuafe.com');
        $client->loginUser($customer);

        $client->catchExceptions(true);
        $client->request('GET', '/admin');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testCustomerAccessToStaffIsForbidden(): void
    {
        $client = static::createClient();
        $customer = $this->getCustomerByEmail('customer@fuafe.com');
        $client->loginUser($customer);

        $client->catchExceptions(true);
        $client->request('GET', '/staff');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testStaffAccessToStaffIsSuccessful(): void
    {
        $client = static::createClient();
        $staff = $this->getCustomerByEmail('staff@fuafe.com');
        $client->loginUser($staff);

        $client->request('GET', '/staff');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Staff Portal');
        $this->assertSelectorTextContains('.navbar', 'STAFF');
        $this->assertSelectorTextContains('.nav-links', 'Staff Portal');
    }

    public function testStaffAccessToAdminIsForbidden(): void
    {
        $client = static::createClient();
        $staff = $this->getCustomerByEmail('staff@fuafe.com');
        $client->loginUser($staff);

        $client->catchExceptions(true);
        $client->request('GET', '/admin');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testAdminAccessToAdminIsSuccessful(): void
    {
        $client = static::createClient();
        $admin = $this->getCustomerByEmail('admin@fuafe.com');
        $client->loginUser($admin);

        $client->request('GET', '/admin');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1.page-title', 'Admin Dashboard');
        $this->assertSelectorTextContains('.navbar', 'ADMIN');
        $this->assertSelectorTextContains('.nav-links', 'Admin Panel');
    }

    public function testAdminAccessToStaffIsSuccessfulViaRoleHierarchy(): void
    {
        $client = static::createClient();
        $admin = $this->getCustomerByEmail('admin@fuafe.com');
        $client->loginUser($admin);

        $client->request('GET', '/staff');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Staff Portal');
    }

    public function testInactiveAccountCannotLogin(): void
    {
        $client = static::createClient();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $customerRepo = $em->getRepository(Customer::class);

        $inactiveUser = $customerRepo->findOneBy(['email' => 'disabled@fuafe.com']);
        if (!$inactiveUser) {
            $hasher = static::getContainer()->get('security.user_password_hasher');
            $inactiveUser = new Customer();
            $inactiveUser->setEmail('disabled@fuafe.com');
            $inactiveUser->setUsername('Disabled User');
            $inactiveUser->setAge(35);
            $inactiveUser->setLocation('Dumaguete');
            $inactiveUser->setRoles(['ROLE_USER']);
            $inactiveUser->setIsActive(false);
            $inactiveUser->setPassword($hasher->hashPassword($inactiveUser, 'password123'));
            $em->persist($inactiveUser);
            $em->flush();
        } else {
            $inactiveUser->setIsActive(false);
            $em->flush();
        }

        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Log In')->form([
            'email' => 'disabled@fuafe.com',
            'password' => 'password123',
        ]);
        $client->submit($form);

        $this->assertResponseRedirects('/login');
        $crawler = $client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.auth-card', 'Your account has been deactivated. Please contact support.');
    }

    public function testAnonymousNavbarHasNoStaffOrAdminLinks(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorNotExists('.nav-links a:contains("Admin Panel")');
        $this->assertSelectorNotExists('.nav-links a:contains("Staff Portal")');
    }
}
