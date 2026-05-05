<?php

declare(strict_types=1);

namespace App\Shared\Controller;

use App\Shared\Entity\Category;
use App\Shared\Repository\CategoryRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class EditCategoryController
{
    public function __construct(
        private readonly ControllerHelper $controller,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly RequestStack $requestStack,
    ) {
    }

    #[Route('/categories/{id}/edit', name: 'app_categories_edit', methods: ['GET', 'POST'])]
    public function __invoke(int $id): Response
    {
        $category = $this->categoryRepository->findById($id);
        if (! $category instanceof Category) {
            $this->controller->addFlash('error', 'Category not found.');

            return new RedirectResponse($this->urlGenerator->generate('app_categories'));
        }

        $request = $this->requestStack->getCurrentRequest();
        if ($request instanceof Request && $request->isMethod('POST')) {
            return $this->handlePost($request, $category, $id);
        }

        return $this->controller->render('category/edit.html.twig', [
            'category' => $category,
        ]);
    }

    private function handlePost(
        Request $request,
        Category $category,
        int $id,
    ): Response {
        if (! $this->controller->isCsrfTokenValid('edit_category', (string) $request->request->get('_token'))) {
            $this->controller->addFlash('error', 'Invalid CSRF token.');

            return new RedirectResponse($this->urlGenerator->generate('app_categories_edit', [
                'id' => $id,
            ]));
        }

        $name = trim((string) $request->request->get('name'));
        $color = trim((string) $request->request->get('color'));
        $weight = (int) $request->request->get('weight');
        $fetchInterval = trim((string) $request->request->get('fetch_interval_minutes'));

        if ($name === '' || $color === '') {
            return $this->controller->render('category/edit.html.twig', [
                'category' => $category,
                'error' => 'Name and color are required.',
                'formData' => [
                    'name' => $name,
                    'weight' => $weight,
                    'color' => $color !== '' ? $color : $category->getColor(),
                    'fetch_interval_minutes' => $fetchInterval,
                ],
            ]);
        }

        $category->setName($name);
        $category->setWeight($weight);
        $category->setColor($color);
        $category->setFetchIntervalMinutes($this->parseFetchInterval($fetchInterval));

        $this->categoryRepository->save($category, flush: true);

        $this->controller->addFlash('success', 'Category updated.');

        return new RedirectResponse($this->urlGenerator->generate('app_categories'));
    }

    private function parseFetchInterval(string $input): ?int
    {
        if ($input === '') {
            return null;
        }

        $value = (int) $input;

        return ($value >= 5 && $value <= 1440) ? $value : null;
    }
}
