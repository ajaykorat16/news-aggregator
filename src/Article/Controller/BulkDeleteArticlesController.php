<?php

declare(strict_types=1);

namespace App\Article\Controller;

use App\Article\Entity\Article;
use App\Article\Repository\ArticleRepositoryInterface;
use App\User\Entity\User;
use App\User\Repository\UserArticleBookmarkRepositoryInterface;
use App\User\Repository\UserArticleReadRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class BulkDeleteArticlesController
{
    public function __construct(
        private readonly ControllerHelper $controller,
        private readonly ArticleRepositoryInterface $articleRepository,
        private readonly UserArticleReadRepositoryInterface $userArticleReadRepository,
        private readonly UserArticleBookmarkRepositoryInterface $userArticleBookmarkRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[Route('/articles/bulk-delete', name: 'app_articles_bulk_delete', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $user = $this->controller->getUser();
        if (! $user instanceof User) {
            return new RedirectResponse($this->urlGenerator->generate('app_login'));
        }

        $token = $request->headers->get('X-CSRF-Token')
            ?? $request->request->getString('_token');
        if (! $this->controller->isCsrfTokenValid('bulk_delete_articles', $token)) {
            if ($request->headers->has('HX-Request')) {
                return new Response('Invalid CSRF token.', Response::HTTP_FORBIDDEN);
            }

            return new RedirectResponse($this->urlGenerator->generate('app_dashboard'));
        }

        /** @var list<string> $rawIds */
        $rawIds = $request->request->all('article_ids');
        $ids = array_map(intval(...), $rawIds);
        $ids = array_filter($ids, static fn (int $id): bool => $id > 0);

        if ($ids === []) {
            if ($request->headers->has('HX-Request')) {
                return new Response('No articles selected.', Response::HTTP_BAD_REQUEST);
            }

            return new RedirectResponse($this->urlGenerator->generate('app_dashboard'));
        }

        $this->articleRepository->removeByIds(array_values($ids));

        if ($request->headers->has('HX-Request')) {
            return $this->renderArticleFeed($user);
        }

        $this->controller->addFlash('success', sprintf('%d article(s) deleted.', \count($ids)));

        return new RedirectResponse($this->urlGenerator->generate('app_dashboard'));
    }

    private function renderArticleFeed(User $user): Response
    {
        $limit = 20;
        $articles = $this->articleRepository->findPaginated(null, null, 1, $limit);

        $articleIds = array_map(
            static fn (Article $a): int => (int) $a->getId(),
            $articles,
        );

        $readArticleIds = $this->userArticleReadRepository->findReadArticleIdsForUser(
            $user,
            $articleIds,
        );

        $bookmarkedArticleIds = $this->userArticleBookmarkRepository->getBookmarkedArticleIds(
            $user,
            $articleIds,
        );

        return $this->controller->render('dashboard/_article_list.html.twig', [
            'articles' => $articles,
            'readArticleIds' => $readArticleIds,
            'bookmarkedArticleIds' => $bookmarkedArticleIds,
            'currentPage' => 1,
            'currentCategory' => null,
            'unreadOnly' => false,
            'hasMore' => \count($articles) >= $limit,
        ]);
    }
}
