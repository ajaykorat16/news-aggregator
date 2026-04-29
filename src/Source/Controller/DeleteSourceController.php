<?php

declare(strict_types=1);

namespace App\Source\Controller;

use App\Source\Entity\Source;
use App\Source\Repository\SourceRepositoryInterface;
use App\User\Entity\User;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class DeleteSourceController
{
    public function __construct(
        private readonly ControllerHelper $controller,
        private readonly SourceRepositoryInterface $sourceRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[Route('/sources/{id}/delete', name: 'app_sources_delete', methods: ['POST'])]
    public function __invoke(Request $request, int $id): Response
    {
        $user = $this->controller->getUser();
        if (! $user instanceof User) {
            return new RedirectResponse($this->urlGenerator->generate('app_login'));
        }

        $isHtmx = $request->headers->has('HX-Request');

        $token = $request->headers->get('X-CSRF-Token')
            ?? $request->request->getString('_token');
        if (! $this->controller->isCsrfTokenValid('delete_source', $token)) {
            return $this->errorResponse($isHtmx, 'Invalid CSRF token.', Response::HTTP_FORBIDDEN);
        }

        $source = $this->sourceRepository->findById($id);
        if (! $source instanceof Source) {
            return $this->errorResponse($isHtmx, 'Source not found.', Response::HTTP_NOT_FOUND);
        }

        try {
            $this->sourceRepository->remove($source, flush: true);
        } catch (ForeignKeyConstraintViolationException) {
            return $this->handleForeignKeyViolation($isHtmx);
        }

        if ($isHtmx) {
            return new Response('');
        }

        $this->controller->addFlash('success', 'Source deleted.');

        return new RedirectResponse($this->urlGenerator->generate('app_sources'));
    }

    private function errorResponse(bool $isHtmx, string $message, int $statusCode): Response
    {
        if ($isHtmx) {
            return new Response($message, $statusCode);
        }

        $this->controller->addFlash('error', $message);

        return new RedirectResponse($this->urlGenerator->generate('app_sources'));
    }

    private function handleForeignKeyViolation(bool $isHtmx): Response
    {
        $this->controller->addFlash('error', 'Cannot delete source: it still has articles. Remove the articles first.');

        if ($isHtmx) {
            return new Response('', Response::HTTP_OK, [
                'HX-Redirect' => $this->urlGenerator->generate('app_sources'),
            ]);
        }

        return new RedirectResponse($this->urlGenerator->generate('app_sources'));
    }
}
