<?php

declare(strict_types=1);

namespace App\Shared\Controller;

use App\Shared\Entity\Category;
use App\Shared\Repository\CategoryRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class DeleteCategoryController
{
    public function __construct(
        private readonly ControllerHelper $controller,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[Route('/categories/{id}/delete', name: 'app_categories_delete', methods: ['POST'])]
    public function __invoke(Request $request, int $id): Response
    {
        $isHtmx = $request->headers->has('HX-Request');

        $token = $request->headers->get('X-CSRF-Token')
            ?? $request->request->getString('_token');
        if (! $this->controller->isCsrfTokenValid('delete_category', $token)) {
            return $this->errorResponse($isHtmx, 'Invalid CSRF token.', Response::HTTP_FORBIDDEN);
        }

        $category = $this->categoryRepository->findById($id);
        if (! $category instanceof Category) {
            return $this->errorResponse($isHtmx, 'Category not found.', Response::HTTP_NOT_FOUND);
        }

        $this->categoryRepository->remove($category, flush: true);

        if ($isHtmx) {
            return new Response('');
        }

        $this->controller->addFlash('success', 'Category deleted.');

        return new RedirectResponse($this->urlGenerator->generate('app_categories'));
    }

    private function errorResponse(bool $isHtmx, string $message, int $statusCode): Response
    {
        if ($isHtmx) {
            return new Response($message, $statusCode);
        }

        $this->controller->addFlash('error', $message);

        return new RedirectResponse($this->urlGenerator->generate('app_categories'));
    }
}
