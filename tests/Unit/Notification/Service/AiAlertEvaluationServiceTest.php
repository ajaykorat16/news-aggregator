<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Service;

use App\Article\Entity\Article;
use App\Notification\Entity\AlertRule;
use App\Notification\Service\AiAlertEvaluationService;
use App\Notification\ValueObject\AlertRuleType;
use App\Shared\Entity\Category;
use App\Source\Entity\Source;
use App\User\Entity\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\AI\Platform\PlatformInterface;

#[CoversClass(AiAlertEvaluationService::class)]
final class AiAlertEvaluationServiceTest extends TestCase
{
    public function testFallsBackToRuleBasedOnAiFailure(): void
    {
        $platform = $this->createStub(PlatformInterface::class);
        $platform->method('invoke')->willThrowException(new \RuntimeException('API down'));

        $service = new AiAlertEvaluationService($platform, new NullLogger());
        $result = $service->evaluate($this->createArticle(), $this->createRule());

        self::assertNotNull($result);
        self::assertGreaterThanOrEqual(1, $result->severity);
        self::assertLessThanOrEqual(10, $result->severity);
        self::assertNull($result->modelUsed); // Rule-based has no model
    }

    public function testFallsBackWhenNoContextPrompt(): void
    {
        $platform = $this->createStub(PlatformInterface::class);
        $service = new AiAlertEvaluationService($platform, new NullLogger());

        $user = new User('admin@example.com', 'hashed');
        $rule = new AlertRule('No Context', AlertRuleType::Ai, $user, new \DateTimeImmutable());
        // No context prompt set

        $result = $service->evaluate($this->createArticle(), $rule);

        self::assertNotNull($result);
    }

    private function createArticle(): Article
    {
        $category = new Category('Tech', 'tech', 10, '#3B82F6');
        $source = new Source('Src', 'https://example.com/feed', $category, new \DateTimeImmutable());
        $article = new Article('Critical Security Flaw', 'https://example.com/1', $source, new \DateTimeImmutable());
        $article->setSummary('A critical vulnerability was discovered in major software.');

        return $article;
    }

    private function createRule(): AlertRule
    {
        $user = new User('admin@example.com', 'hashed');
        $rule = new AlertRule('Security Alert', AlertRuleType::Ai, $user, new \DateTimeImmutable());
        $rule->setKeywords(['vulnerability', 'security']);
        $rule->setContextPrompt('Monitor for critical cybersecurity vulnerabilities affecting enterprise software');

        return $rule;
    }
}
