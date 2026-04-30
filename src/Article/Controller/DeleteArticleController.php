<?php

declare(strict_types=1);

namespace App\Article\Controller;

use App\Article\Entity\Article;
use App\Article\Repository\ArticleRepositoryInterface;
use App\User\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class DeleteArticleController
{
    public function __construct(
        private readonly ControllerHelper $controller,
        private readonly ArticleRepositoryInterface $articleRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[Route('/articles/{id}/delete', name: 'app_article_delete', methods: ['POST'])]
    public function __invoke(Request $request, int $id): Response
    {
        $user = $this->controller->getUser();
        if (! $user instanceof User) {
            return new RedirectResponse($this->urlGenerator->generate('app_login'));
        }

        $isHtmx = $request->headers->has('HX-Request');

        $token = $request->headers->get('X-CSRF-Token')
            ?? $request->request->getString('_token');
        if (! $this->controller->isCsrfTokenValid('delete_article', $token)) {
            if ($isHtmx) {
                return new Response('Invalid CSRF token.', Response::HTTP_FORBIDDEN);
            }

            $this->controller->addFlash('error', 'Invalid CSRF token.');

            return new RedirectResponse($this->urlGenerator->generate('app_dashboard'));
        }

        $article = $this->articleRepository->findById($id);
        if (! $article instanceof Article) {
            if ($isHtmx) {
                return new Response('Article not found.', Response::HTTP_NOT_FOUND);
            }

            $this->controller->addFlash('error', 'Article not found.');

            return new RedirectResponse($this->urlGenerator->generate('app_dashboard'));
        }

        $this->articleRepository->remove($article, flush: true);

        if ($isHtmx) {
            return new Response('');
        }

        $this->controller->addFlash('success', 'Article deleted.');

        return new RedirectResponse($this->urlGenerator->generate('app_dashboard'));
    }
}
