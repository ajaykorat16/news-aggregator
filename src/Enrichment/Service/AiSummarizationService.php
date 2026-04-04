<?php

declare(strict_types=1);

namespace App\Enrichment\Service;

use Psr\Log\LoggerInterface;
use Symfony\AI\Platform\PlatformInterface;

final readonly class AiSummarizationService implements SummarizationServiceInterface
{
    private const string MODEL = 'openrouter/auto';

    private const string PROMPT_TEMPLATE = <<<'PROMPT'
Summarize the following news article in 1-2 concise sentences. Focus on the key facts.

Content: %s
PROMPT;

    public function __construct(
        private PlatformInterface $platform,
        private RuleBasedSummarizationService $ruleBasedFallback,
        private LoggerInterface $logger,
    ) {
    }

    public function summarize(string $contentText): ?string
    {
        try {
            $prompt = sprintf(self::PROMPT_TEMPLATE, mb_substr($contentText, 0, 2000));

            $summary = trim($this->platform->invoke(self::MODEL, $prompt)->asText());

            $length = mb_strlen($summary);
            if ($length >= 20 && $length <= 500) {
                return $summary;
            }

            $this->logger->info('AI summary rejected: {length} chars (expected 20-500)', [
                'length' => $length,
            ]);
        } catch (\Throwable $e) {
            $this->logger->warning('AI summarization failed, using rule-based fallback: {error}', [
                'error' => $e->getMessage(),
            ]);
        }

        return $this->ruleBasedFallback->summarize($contentText);
    }
}
