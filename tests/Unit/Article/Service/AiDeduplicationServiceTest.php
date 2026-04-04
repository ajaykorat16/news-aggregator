<?php

declare(strict_types=1);

namespace App\Tests\Unit\Article\Service;

use App\Article\Service\AiDeduplicationService;
use App\Article\Service\DeduplicationServiceInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\AI\Platform\PlatformInterface;

#[CoversClass(AiDeduplicationService::class)]
final class AiDeduplicationServiceTest extends TestCase
{
    public function testDelegatesUrlCheckToRuleBased(): void
    {
        $ruleBased = $this->createStub(DeduplicationServiceInterface::class);
        $ruleBased->method('isDuplicate')->willReturn(true);

        $platform = $this->createStub(PlatformInterface::class);

        $service = new AiDeduplicationService($ruleBased, $platform, new NullLogger());

        self::assertTrue($service->isDuplicate('https://example.com/1', 'Title', null));
    }

    public function testReturnsFalseWhenRuleBasedSaysNotDuplicate(): void
    {
        $ruleBased = $this->createStub(DeduplicationServiceInterface::class);
        $ruleBased->method('isDuplicate')->willReturn(false);

        $platform = $this->createStub(PlatformInterface::class);

        $service = new AiDeduplicationService($ruleBased, $platform, new NullLogger());

        self::assertFalse($service->isDuplicate('https://example.com/new', 'Unique Title', null));
    }

    public function testSemanticDuplicateHandlesAiFailure(): void
    {
        $ruleBased = $this->createStub(DeduplicationServiceInterface::class);

        $platform = $this->createStub(PlatformInterface::class);
        $platform->method('invoke')->willThrowException(new \RuntimeException('API down'));

        $service = new AiDeduplicationService($ruleBased, $platform, new NullLogger());

        // Should return false on AI failure (safe default)
        self::assertFalse($service->isSemanticallyDuplicate('Title A', 'Title B'));
    }
}
