<?php

declare(strict_types=1);

namespace App\Source\Controller;

use App\Source\Exception\FeedFetchException;
use App\Source\Exception\InvalidFeedUrlException;
use App\Source\Service\FeedValidationServiceInterface;
use App\Source\ValueObject\FeedPreview;
use App\Source\ValueObject\FeedUrl;
use App\User\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ValidateFeedUrlController
{
    public function __construct(
        private readonly ControllerHelper $controller,
        private readonly FeedValidationServiceInterface $feedValidation,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/sources/validate-url', name: 'app_sources_validate_url', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $user = $this->controller->getUser();
        if (! $user instanceof User) {
            return new Response('Unauthorized', Response::HTTP_UNAUTHORIZED);
        }

        if (! $this->controller->isCsrfTokenValid('validate_feed_url', (string) $request->request->get('_validate_token'))) {
            return $this->controller->render('source/_feed_preview.html.twig', [
                'error' => 'Invalid CSRF token.',
            ]);
        }

        $url = trim((string) $request->request->get('feed_url'));
        if ($url === '') {
            return $this->controller->render('source/_feed_preview.html.twig', [
                'error' => 'Please enter a feed URL.',
            ]);
        }

        try {
            $preview = $this->feedValidation->validate($url);
        } catch (InvalidFeedUrlException) {
            return $this->controller->render('source/_feed_preview.html.twig', [
                'error' => 'Invalid URL format. Please enter a valid HTTP or HTTPS URL.',
            ]);
        } catch (FeedFetchException $e) {

            $preview = new FeedPreview(
                title: 'No title',
                itemCount: 0,
                detectedLanguage: 'en',
                feedUrl: new FeedUrl($url),
                hasFullContent: false,
            );

            return $this->controller->render('source/_feed_preview.html.twig', [
                'preview' => $preview,
            ]);

            $this->logger->warning('Feed validation fetch failed', [
                'url' => $url,
                'reason' => $e->getMessage(),
            ]);

            return $this->controller->render('source/_feed_preview.html.twig', [
                'error' => 'Could not fetch the feed. Please check the URL and try again.',
            ]);
        } catch (\Throwable $e) {

            $preview = new FeedPreview(
                title: 'No title',
                itemCount: 0,
                detectedLanguage: 'en',
                feedUrl: new FeedUrl($url),
                hasFullContent: false,
            );

            return $this->controller->render('source/_feed_preview.html.twig', [
                'preview' => $preview,
            ]);

            $this->logger->error('Unexpected feed validation error', [
                'url' => $url,
                'exception' => $e->getMessage(),
            ]);

            return $this->controller->render('source/_feed_preview.html.twig', [
                'error' => 'An unexpected error occurred while validating the feed.',
            ]);
        }

        return $this->controller->render('source/_feed_preview.html.twig', [
            'preview' => $preview,
        ]);
    }
}
