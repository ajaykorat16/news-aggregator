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

final class CreateCategoryController
{
    public function __construct(
        private readonly ControllerHelper $controller,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly RequestStack $requestStack,
    ) {
    }

    #[Route('/categories/new', name: 'app_categories_new', methods: ['GET', 'POST'])]
    public function __invoke(): Response
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request instanceof Request && $request->isMethod('POST')) {
            return $this->handlePost($request);
        }

        return $this->controller->render('category/new.html.twig');
    }

    private function handlePost(Request $request): Response
    {
        $name = trim((string) $request->request->get('name'));
        $slug = trim((string) $request->request->get('slug'));
        $weight = (int) $request->request->get('weight');
        $color = trim((string) $request->request->get('color'));
        $fetchInterval = trim((string) $request->request->get('fetch_interval_minutes'));

        $formData = [
            'name' => $name,
            'slug' => $slug,
            'weight' => $weight,
            'color' => $color !== '' ? $color : '#3B82F6',
            'fetch_interval_minutes' => $fetchInterval,
        ];

        if ($name === '' || $slug === '' || $color === '') {
            return $this->controller->render('category/new.html.twig', [
                'error' => 'Name, slug, and color are required.',
                'formData' => $formData,
            ]);
        }

        if ($this->categoryRepository->findBySlug($slug) instanceof Category) {
            return $this->controller->render('category/new.html.twig', [
                'error' => 'A category with this slug already exists.',
                'formData' => $formData,
            ]);
        }

        $category = new Category($name, $slug, $weight, $color);
        $category->setFetchIntervalMinutes($this->parseFetchInterval($fetchInterval));

        $this->categoryRepository->save($category, flush: true);

        $this->controller->addFlash('success', 'Category created.');

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
