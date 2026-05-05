<?php

declare(strict_types=1);

namespace App\Tests\Functional\Shared\Controller;

use App\Shared\Entity\Category;
use App\Shared\Repository\CategoryRepository;
use App\Shared\Repository\CategoryRepositoryInterface;
use App\User\Entity\User;
use App\User\Repository\UserRepository;
use App\User\Repository\UserRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[CoversNothing]
final class CategoryCrudTest extends WebTestCase
{
    public function testListRendersSuccessfully(): void
    {
        $client = self::createClient();
        $client->loginUser($this->getOrCreateUser());

        $client->request('GET', '/categories');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('h1');
    }

    public function testNewFormRendersSuccessfully(): void
    {
        $client = self::createClient();
        $client->loginUser($this->getOrCreateUser());

        $client->request('GET', '/categories/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
        self::assertSelectorExists('input[name="name"]');
        self::assertSelectorExists('input[name="slug"]');
    }

    public function testCreateCategoryAndRedirects(): void
    {
        $client = self::createClient();
        $client->loginUser($this->getOrCreateUser());

        $slug = 'test-create-' . uniqid();

        $client->request('POST', '/categories/new', [
            'name' => 'Test Create Category',
            'slug' => $slug,
            'weight' => '10',
            'color' => '#FF5733',
        ]);

        self::assertResponseRedirects('/categories');

        $repository = $this->getCategoryRepository();
        $category = $repository->findBySlug($slug);

        self::assertNotNull($category);
        self::assertSame('Test Create Category', $category->getName());
        self::assertSame($slug, $category->getSlug());
        self::assertSame(10, $category->getWeight());
        self::assertSame('#FF5733', $category->getColor());
    }

    public function testCreateWithDuplicateSlugShowsError(): void
    {
        $client = self::createClient();
        $client->loginUser($this->getOrCreateUser());

        $slug = 'duplicate-slug-' . uniqid();
        $this->createCategoryWithSlug($slug);

        $client->request('POST', '/categories/new', [
            'name' => 'Another Category',
            'slug' => $slug,
            'weight' => '5',
            'color' => '#000000',
        ]);

        self::assertResponseRedirects('/categories/new');
    }

    public function testEditFormRendersPrefilledValues(): void
    {
        $client = self::createClient();
        $client->loginUser($this->getOrCreateUser());

        $slug = 'edit-form-' . uniqid();
        $category = $this->createCategoryWithSlug($slug, 'Edit Form Test', 15, '#AABBCC');
        $id = $category->getId();
        self::assertNotNull($id);

        $client->request('GET', '/categories/' . $id . '/edit');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="name"][value="Edit Form Test"]');
        self::assertSelectorExists('input[name="weight"][value="15"]');
    }

    public function testEditCategoryUpdatesAndRedirects(): void
    {
        $client = self::createClient();
        $client->loginUser($this->getOrCreateUser());

        $slug = 'edit-update-' . uniqid();
        $category = $this->createCategoryWithSlug($slug, 'Original Name', 5, '#000000');
        $id = $category->getId();
        self::assertNotNull($id);

        $csrfToken = $this->getCsrfTokenFromEditPage($client, $id);

        $client->request('POST', '/categories/' . $id . '/edit', [
            '_token' => $csrfToken,
            'name' => 'Updated Name',
            'weight' => '20',
            'color' => '#FF0000',
            'fetch_interval_minutes' => '30',
        ]);

        self::assertResponseRedirects('/categories');

        $repository = $this->getCategoryRepository();
        $updated = $repository->findById($id);

        self::assertNotNull($updated);
        self::assertSame('Updated Name', $updated->getName());
        self::assertSame(20, $updated->getWeight());
        self::assertSame('#FF0000', $updated->getColor());
        self::assertSame(30, $updated->getFetchIntervalMinutes());
    }

    public function testDeleteCategoryRemovesRow(): void
    {
        $client = self::createClient();
        $client->loginUser($this->getOrCreateUser());

        $slug = 'delete-test-' . uniqid();
        $category = $this->createCategoryWithSlug($slug);
        $id = $category->getId();
        self::assertNotNull($id);

        $csrfToken = $this->getCsrfTokenFromCategoriesPage($client, $id);

        $client->request('POST', '/categories/' . $id . '/delete', [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects('/categories');

        // Clear identity map to force fresh DB query
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        $repository = $this->getCategoryRepository();
        self::assertNull($repository->findById($id));
    }

    private function getOrCreateUser(): User
    {
        /** @var UserRepository $repository */
        $repository = self::getContainer()->get(UserRepositoryInterface::class);

        $user = $repository->findFirst();
        if (! $user instanceof User) {
            $user = new User('test@example.com', 'hashed');
            $repository->save($user, flush: true);
        }

        $user->setRoles(['ROLE_ADMIN']);
        $repository->save($user, flush: true);

        return $user;
    }

    private function getCategoryRepository(): CategoryRepository
    {
        /** @var CategoryRepository $repository */
        $repository = self::getContainer()->get(CategoryRepositoryInterface::class);

        return $repository;
    }

    private function createCategoryWithSlug(
        string $slug,
        string $name = 'Test Category',
        int $weight = 0,
        string $color = '#000000',
    ): Category {
        $repository = $this->getCategoryRepository();

        $category = new Category($name, $slug, $weight, $color);
        $repository->save($category, flush: true);

        return $category;
    }

    private function getCsrfTokenFromEditPage(KernelBrowser $client, int $id): string
    {
        $crawler = $client->request('GET', '/categories/' . $id . '/edit');

        return $crawler->filter('input[name="_token"]')->attr('value') ?? '';
    }

    private function getCsrfTokenFromCategoriesPage(KernelBrowser $client, int $id): string
    {
        $crawler = $client->request('GET', '/categories');
        $deleteBtn = $crawler->filter('button[hx-post$="/' . $id . '/delete"]')->first();
        /** @var array<string, string> $headers */
        $headers = json_decode($deleteBtn->attr('hx-headers') ?? '{}', true, 512, JSON_THROW_ON_ERROR);

        return $headers['X-CSRF-Token'];
    }
}
