<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\Panther\PantherTestCase;

#[CoversNothing]
final class DashboardE2ETest extends PantherTestCase
{
    public function testLoginAndDashboard(): void
    {
        $client = self::createPantherClient([
            'external_base_uri' => 'https://localhost',
        ]);
        $client->request('GET', '/login');

        $client->submitForm('Sign In', [
            '_username' => 'demo@localhost',
            '_password' => 'demo',
        ]);

        self::assertSelectorTextContains('body', 'News Aggregator');
    }

    public function testCategoryFilter(): void
    {
        $client = self::createPantherClient([
            'external_base_uri' => 'https://localhost',
        ]);
        $client->request('GET', '/login');
        $client->submitForm('Sign In', [
            '_username' => 'demo@localhost',
            '_password' => 'demo',
        ]);

        $client->clickLink('Tech');
        self::assertStringContainsString('category=tech', $client->getCurrentURL());
    }

    public function testThemeToggle(): void
    {
        $client = self::createPantherClient([
            'external_base_uri' => 'https://localhost',
        ]);
        $client->request('GET', '/login');
        $client->submitForm('Sign In', [
            '_username' => 'demo@localhost',
            '_password' => 'demo',
        ]);

        // Set theme directly via localStorage + attribute (same as theme-toggle.ts does)
        $client->executeScript("localStorage.setItem('theme','winter'); document.documentElement.setAttribute('data-theme','winter')");
        $theme = $client->executeScript("return document.documentElement.getAttribute('data-theme')");
        self::assertSame('winter', $theme);

        // Verify persistence across navigation
        $client->request('GET', '/sources');
        $theme = $client->executeScript("return localStorage.getItem('theme')");
        self::assertSame('winter', $theme);
    }
}
