<?php

declare(strict_types=1);

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class DemoControllerTest extends WebTestCase
{
    public function testHomePageIsSuccessful(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Home');
        self::assertSelectorTextContains('body', 'Page Builder Kit Bundle');
    }

    public function testContactPageIsSuccessful(): void
    {
        $client = static::createClient();
        $client->request('GET', '/contact');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Contact');
    }

    public function testLoginPageIsSuccessful(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Admin login');
        self::assertSelectorTextContains('body', 'admin');
    }
}
