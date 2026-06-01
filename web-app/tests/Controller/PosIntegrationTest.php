<?php
// tests/Controller/PosIntegrationTest.php

namespace App\Tests\Controller;

use App\Entity\Product;
use App\Entity\User;
use App\Entity\UserRole;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class PosIntegrationTest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return \App\Kernel::class;
    }

    public function testReceptionistCheckoutFlow()
    {
        if (false === getenv('DATABASE_URL_TEST')) {
            $this->markTestSkipped('DATABASE_URL_TEST not set; skipping integration test.');
            return;
        }

        $client = static::createClient();
        $em = $client->getContainer()->get('doctrine')->getManager();

        // Create product fixture
        $product = new Product();
        $product->setName('Test Widget');
        $product->setCategory('Test');
        $product->setBarcode('TEST-001');
        $product->setUnitPrice(100.0);
        $product->setStockQuantity(10);
        $product->setReorderLevel(2);
        $product->setCostPrice(60.0);
        $em->persist($product);

        // Create staff user
        $user = new User();
        $user->setUsername('teststaff');
        $user->setEmail('staff@example.test');
        $user->setPassword('none');
        $user->setRole(UserRole::ROLE_STAFF);
        $em->persist($user);

        $em->flush();

        // Login the created user
        $client->loginUser($user);

        // Perform checkout
        $payload = ['items' => [['productId' => $product->getId(), 'quantity' => 2]]];
        $client->request('POST', '/pos/api/transaction/create', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload));
        $resp = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('success', $resp);
        $this->assertTrue($resp['success']);

        // Verify stock deduction
        $em->refresh($product);
        $this->assertEquals(8, $product->getStockQuantity());
    }
}
