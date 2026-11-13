<?php

namespace App\Tests\Controller;

use App\Entity\Comment;
use App\Entity\Customer;
use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AdminFeaturesTest extends WebTestCase
{
    private function getAdminUser(): Customer
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $admin = $em->getRepository(Customer::class)->findOneBy(['email' => 'admin@fuafe.com']);
        $this->assertNotNull($admin, 'Admin account admin@fuafe.com should exist.');
        return $admin;
    }

    public function testAdminDashboardDisplaysMetrics(): void
    {
        $client = static::createClient();
        $admin = $this->getAdminUser();
        $client->loginUser($admin);

        $client->request('GET', '/admin');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1.page-title', 'Admin Dashboard');
        $this->assertSelectorTextContains('.admin-main', 'Total Accounts');
        $this->assertSelectorTextContains('.admin-main', 'Menu Products');
        $this->assertSelectorTextContains('.admin-main', 'Customer Reviews');
        $this->assertSelectorExists('.admin-sidebar');
    }

    public function testAdminCanViewUsersList(): void
    {
        $client = static::createClient();
        $admin = $this->getAdminUser();
        $client->loginUser($admin);

        $client->request('GET', '/admin/users');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1.page-title', 'User & Staff Management');
        $this->assertSelectorExists('.admin-table');
        $this->assertSelectorTextContains('.admin-table', 'admin@fuafe.com');
    }

    public function testAdminCanCreateNewUserAndStaff(): void
    {
        $client = static::createClient();
        $admin = $this->getAdminUser();
        $client->loginUser($admin);

        $crawler = $client->request('GET', '/admin/users/new');
        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton('Create Account')->form([
            'username' => 'Test Barista Carlos',
            'email' => 'carlos_test@fuafe.com',
            'role' => 'ROLE_STAFF',
            'password' => 'carlos123',
            'age' => 24,
            'location' => 'Dumaguete City',
        ]);

        $client->submit($form);
        $this->assertResponseRedirects('/admin/users');

        $client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.admin-main', 'Test Barista Carlos');

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $user = $em->getRepository(Customer::class)->findOneBy(['email' => 'carlos_test@fuafe.com']);
        $this->assertNotNull($user);
        $this->assertContains('ROLE_STAFF', $user->getRoles());
        $this->assertTrue($user->isActive());
    }

    public function testAdminCanEditUser(): void
    {
        $client = static::createClient();
        $admin = $this->getAdminUser();
        $client->loginUser($admin);

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $user = $em->getRepository(Customer::class)->findOneBy(['email' => 'carlos_test@fuafe.com']);
        $this->assertNotNull($user);

        $crawler = $client->request('GET', '/admin/users/' . $user->getId() . '/edit');
        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton('Save Changes')->form([
            'username' => 'Carlos Senior Barista',
            'location' => 'Valencia, Negros Oriental',
            'role' => 'ROLE_STAFF',
        ]);

        $client->submit($form);
        $this->assertResponseRedirects('/admin/users');

        $client->followRedirect();
        $this->assertResponseIsSuccessful();

        $em->clear();
        $updated = $em->getRepository(Customer::class)->find($user->getId());
        $this->assertSame('Carlos Senior Barista', $updated->getUsername());
        $this->assertSame('Valencia, Negros Oriental', $updated->getLocation());
    }

    public function testAdminCanToggleUserActiveStatus(): void
    {
        $client = static::createClient();
        $admin = $this->getAdminUser();
        $client->loginUser($admin);

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $user = $em->getRepository(Customer::class)->findOneBy(['email' => 'carlos_test@fuafe.com']);
        $this->assertNotNull($user);

        $crawler = $client->request('GET', '/admin/users');
        $toggleForm = $crawler->filter('form[action="/admin/users/' . $user->getId() . '/toggle-active"]');
        $this->assertCount(1, $toggleForm);
        $token = $toggleForm->filter('input[name="_csrf_token"]')->attr('value');

        // Toggle to Deactivated
        $client->request('POST', '/admin/users/' . $user->getId() . '/toggle-active', [
            '_csrf_token' => $token,
        ]);
        $this->assertResponseRedirects('/admin/users');

        $em->clear();
        $user = $em->getRepository(Customer::class)->find($user->getId());
        $this->assertFalse($user->isActive(), 'User should be deactivated');

        // Toggle back to Active
        $crawler = $client->followRedirect();
        $toggleForm = $crawler->filter('form[action="/admin/users/' . $user->getId() . '/toggle-active"]');
        $token = $toggleForm->filter('input[name="_csrf_token"]')->attr('value');

        $client->request('POST', '/admin/users/' . $user->getId() . '/toggle-active', [
            '_csrf_token' => $token,
        ]);
        $this->assertResponseRedirects('/admin/users');

        $em->clear();
        $user = $em->getRepository(Customer::class)->find($user->getId());
        $this->assertTrue($user->isActive(), 'User should be active again');
    }

    public function testAdminCannotDeactivateSelf(): void
    {
        $client = static::createClient();
        $admin = $this->getAdminUser();
        $client->loginUser($admin);

        $client->request('POST', '/admin/users/' . $admin->getId() . '/toggle-active', [
            '_csrf_token' => 'any_token',
        ]);

        $this->assertResponseRedirects('/admin/users');
        $client->followRedirect();
        $this->assertSelectorTextContains('.admin-main', 'You cannot deactivate your own admin account');

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $em->clear();
        $adminReloaded = $em->getRepository(Customer::class)->find($admin->getId());
        $this->assertTrue($adminReloaded->isActive());
    }

    public function testAdminCanDeleteUser(): void
    {
        $client = static::createClient();
        $admin = $this->getAdminUser();
        $client->loginUser($admin);

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $user = $em->getRepository(Customer::class)->findOneBy(['email' => 'carlos_test@fuafe.com']);
        $this->assertNotNull($user);

        $crawler = $client->request('GET', '/admin/users');
        $deleteForm = $crawler->filter('form[action="/admin/users/' . $user->getId() . '/delete"]');
        $token = $deleteForm->filter('input[name="_csrf_token"]')->attr('value');

        $client->request('POST', '/admin/users/' . $user->getId() . '/delete', [
            '_csrf_token' => $token,
        ]);

        $this->assertResponseRedirects('/admin/users');
        $client->followRedirect();
        $this->assertSelectorTextContains('.admin-main', 'has been permanently deleted');

        $em->clear();
        $deleted = $em->getRepository(Customer::class)->find($user->getId());
        $this->assertNull($deleted);
    }

    public function testAdminCanViewAllProducts(): void
    {
        $client = static::createClient();
        $admin = $this->getAdminUser();
        $client->loginUser($admin);

        $client->request('GET', '/admin/products');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1.page-title', 'Global Product Management');
        $this->assertSelectorExists('.admin-table');
        $this->assertSelectorTextContains('.admin-table', 'Espresso');
    }

    public function testAdminCanModerateAndToggleCommentVisibility(): void
    {
        $client = static::createClient();
        $admin = $this->getAdminUser();
        $client->loginUser($admin);

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $comment = $em->getRepository(Comment::class)->findOneBy(['isHidden' => false]);
        $this->assertNotNull($comment);

        $commentId = $comment->getId();

        // 1. Visit admin comments page
        $crawler = $client->request('GET', '/admin/comments');
        $this->assertResponseIsSuccessful();

        $toggleForm = $crawler->filter('form[action="/admin/comments/' . $commentId . '/toggle-visibility"]');
        $token = $toggleForm->filter('input[name="_csrf_token"]')->attr('value');

        // 2. Hide comment
        $client->request('POST', '/admin/comments/' . $commentId . '/toggle-visibility', [
            '_csrf_token' => $token,
        ]);
        $this->assertResponseRedirects('/admin/comments');

        $em->clear();
        $comment = $em->getRepository(Comment::class)->find($commentId);
        $this->assertTrue($comment->isHidden(), 'Comment should now be hidden');

        // 3. Verify hidden comment is NOT shown on public /comments page
        $client->restart();
        $crawler = $client->request('GET', '/comments');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorNotExists('.comment-box:contains("' . substr($comment->getComment(), 0, 20) . '")');

        // 4. Restore visibility
        $client->loginUser($admin);
        $crawler = $client->request('GET', '/admin/comments');
        $toggleForm = $crawler->filter('form[action="/admin/comments/' . $commentId . '/toggle-visibility"]');
        $token = $toggleForm->filter('input[name="_csrf_token"]')->attr('value');

        $client->request('POST', '/admin/comments/' . $commentId . '/toggle-visibility', [
            '_csrf_token' => $token,
        ]);
        $this->assertResponseRedirects('/admin/comments');

        $em->clear();
        $comment = $em->getRepository(Comment::class)->find($commentId);
        $this->assertFalse($comment->isHidden(), 'Comment should now be visible again');
    }

    public function testAdminCanDeleteSpamComment(): void
    {
        $client = static::createClient();
        $admin = $this->getAdminUser();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $customer = $em->getRepository(Customer::class)->findOneBy(['email' => 'customer@fuafe.com']);

        $spamComment = new Comment();
        $spamComment->setCustomer($customer);
        $spamComment->setRating(1);
        $spamComment->setComment('Spam advertising comment for testing removal.');
        $spamComment->setIsHidden(true);
        $spamComment->setCreatedAt(new \DateTimeImmutable());
        $em->persist($spamComment);
        $em->flush();

        $commentId = $spamComment->getId();

        $client->loginUser($admin);
        $crawler = $client->request('GET', '/admin/comments');
        $deleteForm = $crawler->filter('form[action="/admin/comments/' . $commentId . '/delete"]');
        $token = $deleteForm->filter('input[name="_csrf_token"]')->attr('value');

        $client->request('POST', '/admin/comments/' . $commentId . '/delete', [
            '_csrf_token' => $token,
        ]);

        $this->assertResponseRedirects('/admin/comments');
        $client->followRedirect();
        $this->assertSelectorTextContains('.admin-main', 'has been deleted permanently');

        $em->clear();
        $deleted = $em->getRepository(Comment::class)->find($commentId);
        $this->assertNull($deleted);
    }
}
