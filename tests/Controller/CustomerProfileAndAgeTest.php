<?php

namespace App\Tests\Controller;

use App\Entity\Customer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CustomerProfileAndAgeTest extends WebTestCase
{
    private function getCustomerUser(string $email = 'customer@fuafe.com'): Customer
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $customer = $em->getRepository(Customer::class)->findOneBy(['email' => $email]);
        $this->assertNotNull($customer);
        return $customer;
    }

    public function testRegistrationRejectsAgeOver100(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/register');
        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton('Register')->form([
            'registration_form[username]' => 'Old Timer',
            'registration_form[email]' => 'oldtimer@fuafe.com',
            'registration_form[age]' => 105, // Over 100!
            'registration_form[location]' => 'Dumaguete City',
            'registration_form[plainPassword]' => 'password123',
        ]);

        $crawler = $client->submit($form);

        // Should not redirect, instead re-render form with validation error
        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorTextContains('form[name="registration_form"]', 'Age cannot be over 100');

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $user = $em->getRepository(Customer::class)->findOneBy(['email' => 'oldtimer@fuafe.com']);
        $this->assertNull($user, 'User with age > 100 must not be created in database');
    }

    public function testRegistrationAcceptsAge100OrUnder(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/register');
        $this->assertResponseIsSuccessful();

        $uniqueEmail = 'valid_age_' . time() . '@fuafe.com';
        $form = $crawler->selectButton('Register')->form([
            'registration_form[username]' => 'Valid Centenarian',
            'registration_form[email]' => $uniqueEmail,
            'registration_form[age]' => 100, // Valid boundary!
            'registration_form[location]' => 'Dumaguete City',
            'registration_form[plainPassword]' => 'password123',
        ]);

        $client->submit($form);
        $this->assertResponseRedirects('/');

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $user = $em->getRepository(Customer::class)->findOneBy(['email' => $uniqueEmail]);
        $this->assertNotNull($user);
        $this->assertSame(100, $user->getAge());
    }

    public function testAnonymousAccessToProfileRedirectsToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/profile');

        $this->assertResponseRedirects('/login');
    }

    public function testCustomerCanViewProfile(): void
    {
        $client = static::createClient();
        $customer = $this->getCustomerUser();
        $client->loginUser($customer);

        $client->request('GET', '/profile');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Customer Profile');
        $this->assertSelectorTextContains('.admin-section', $customer->getUsername());
        $this->assertSelectorTextContains('.admin-section', $customer->getEmail());
        $this->assertSelectorExists('form[action="/profile/update"]');
        $this->assertSelectorExists('form[action="/profile/change-password"]');
    }

    public function testCustomerCanUpdateProfileDetails(): void
    {
        $client = static::createClient();
        $customer = $this->getCustomerUser();
        $client->loginUser($customer);

        $crawler = $client->request('GET', '/profile');
        $form = $crawler->selectButton('Save Profile Details')->form([
            'username' => 'Fuafe Customer Updated',
            'age' => 28,
            'location' => 'Valencia, Negros Oriental',
        ]);

        $client->submit($form);
        $this->assertResponseRedirects('/profile');

        $client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.admin-section', 'updated successfully');

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $em->clear();
        $reloaded = $em->getRepository(Customer::class)->find($customer->getId());
        $this->assertSame('Fuafe Customer Updated', $reloaded->getUsername());
        $this->assertSame(28, $reloaded->getAge());
        $this->assertSame('Valencia, Negros Oriental', $reloaded->getLocation());
    }

    public function testCustomerCannotUpdateProfileWithAgeOver100(): void
    {
        $client = static::createClient();
        $customer = $this->getCustomerUser();
        $client->loginUser($customer);

        $crawler = $client->request('GET', '/profile');
        $form = $crawler->selectButton('Save Profile Details')->form([
            'username' => 'Fuafe Customer Updated',
            'age' => 120, // Over 100!
            'location' => 'Dumaguete City',
        ]);

        $client->submit($form);
        $this->assertResponseRedirects('/profile');

        $client->followRedirect();
        $this->assertSelectorTextContains('.admin-section', 'Age cannot be over 100');

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $em->clear();
        $reloaded = $em->getRepository(Customer::class)->find($customer->getId());
        $this->assertNotSame(120, $reloaded->getAge());
    }

    public function testCustomerCanChangePassword(): void
    {
        $client = static::createClient();
        $customer = $this->getCustomerUser();
        $client->loginUser($customer);

        $crawler = $client->request('GET', '/profile');
        $form = $crawler->selectButton('Update Password')->form([
            'current_password' => 'password123',
            'new_password' => 'newpassword456',
            'confirm_password' => 'newpassword456',
        ]);

        $client->submit($form);
        $this->assertResponseRedirects('/profile');

        $client->followRedirect();
        $this->assertSelectorTextContains('.admin-section', 'password has been changed successfully');

        // Test login with the newly updated password
        $client->restart();
        $crawler = $client->request('GET', '/login');
        $loginForm = $crawler->selectButton('Log In')->form([
            'email' => $customer->getEmail(),
            'password' => 'newpassword456',
        ]);
        $client->submit($loginForm);
        $this->assertResponseRedirects('/');

        // Revert password back to password123 for other tests
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $hasher = static::getContainer()->get('security.user_password_hasher');
        $customer = $em->getRepository(Customer::class)->find($customer->getId());
        $customer->setPassword($hasher->hashPassword($customer, 'password123'));
        $em->flush();
    }
}
