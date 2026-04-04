<?php

declare(strict_types=1);

namespace App\Article\MessageHandler;

use App\Article\Entity\Article;
use App\Article\Service\DeduplicationServiceInterface;
use App\Article\Service\ScoringServiceInterface;
use App\Article\ValueObject\ArticleFingerprint;
use App\Enrichment\Service\CategorizationServiceInterface;
use App\Enrichment\Service\SummarizationServiceInterface;
use App\Notification\Message\SendNotificationMessage;
use App\Notification\Service\ArticleMatcherServiceInterface;
use App\Shared\Entity\Category;
use App\Shared\ValueObject\EnrichmentMethod;
use App\Source\Entity\Source;
use App\Source\Exception\FeedFetchException;
use App\Source\Message\FetchSourceMessage;
use App\Source\Service\FeedFetcherServiceInterface;
use App\Source\Service\FeedItem;
use App\Source\Service\FeedParserServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class FetchSourceHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private FeedFetcherServiceInterface $feedFetcher,
        private FeedParserServiceInterface $feedParser,
        private DeduplicationServiceInterface $deduplication,
        private CategorizationServiceInterface $categorization,
        private SummarizationServiceInterface $summarization,
        private ScoringServiceInterface $scoring,
        private ArticleMatcherServiceInterface $articleMatcher,
        private MessageBusInterface $messageBus,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(FetchSourceMessage $message): void
    {
        $source = $this->entityManager->find(Source::class, $message->sourceId);
        if ($source === null || ! $source->isEnabled()) {
            return;
        }

        try {
            $feedContent = $this->feedFetcher->fetch($source->getFeedUrl());
            $items = $this->feedParser->parse($feedContent);
            $now = $this->clock->now();
            $persisted = 0;
            /** @var list<Article> $newArticles */
            $newArticles = [];

            foreach ($items as $item) {
                $fingerprint = $item->contentText !== null
                    ? ArticleFingerprint::fromContent($item->contentText)->value
                    : null;

                if ($this->deduplication->isDuplicate($item->url, $item->title, $fingerprint)) {
                    continue;
                }

                $article = $this->buildArticle($item, $source, $now, $fingerprint);
                $this->entityManager->persist($article);
                $newArticles[] = $article;
                $persisted++;
            }

            $source->recordSuccess($now);
            $this->entityManager->flush();

            // Match new articles against alert rules (after flush so articles have IDs)
            $this->dispatchAlerts($newArticles);

            $this->logger->info('Fetched {source}: {count} new articles from {total} items', [
                'source' => $source->getName(),
                'count' => $persisted,
                'total' => \count($items),
            ]);
        } catch (FeedFetchException $e) {
            $source->recordFailure($e->getMessage());
            $this->entityManager->flush();

            $this->logger->warning('Feed fetch failed for {source}: {error}', [
                'source' => $source->getName(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function buildArticle(
        FeedItem $item,
        Source $source,
        \DateTimeImmutable $now,
        ?string $fingerprint,
    ): Article {
        $article = new Article($item->title, $item->url, $source, $now);
        $article->setContentRaw($item->contentRaw);
        $article->setContentText($item->contentText);
        $article->setPublishedAt($item->publishedAt);
        $article->setFingerprint($fingerprint);

        // Rule-based categorization
        $categorySlug = $this->categorization->categorize($item->title, $item->contentText);
        if ($categorySlug !== null) {
            $category = $this->entityManager
                ->getRepository(Category::class)
                ->findOneBy([
                    'slug' => $categorySlug,
                ]);
            if ($category !== null) {
                $article->setCategory($category);
            }
        }

        // Fall back to source category if rule-based didn't match
        if (! $article->getCategory() instanceof \App\Shared\Entity\Category) {
            $article->setCategory($source->getCategory());
        }

        // Rule-based summarization
        if ($item->contentText !== null) {
            $summary = $this->summarization->summarize($item->contentText);
            if ($summary !== null) {
                $article->setSummary($summary);
                $article->setEnrichmentMethod(EnrichmentMethod::RuleBased);
            }
        }

        $article->setScore($this->scoring->score($article));

        return $article;
    }

    /**
     * @param list<Article> $articles
     */
    private function dispatchAlerts(array $articles): void
    {
        foreach ($articles as $article) {
            $articleId = $article->getId();
            if ($articleId === null) {
                continue;
            }

            $matches = $this->articleMatcher->match($article);
            foreach ($matches as $match) {
                $ruleId = $match->alertRule->getId();
                if ($ruleId === null) {
                    continue;
                }

                $this->messageBus->dispatch(
                    new SendNotificationMessage($ruleId, $articleId, $match->matchedKeywords),
                );
            }
        }
    }
}
