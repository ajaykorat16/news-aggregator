<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Entity;

use App\Notification\Entity\AlertRule;
use App\Notification\ValueObject\AlertRuleType;
use App\Notification\ValueObject\AlertUrgency;
use App\User\Entity\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AlertRule::class)]
final class AlertRuleTest extends TestCase
{
    public function testConstructorSetsProperties(): void
    {
        $rule = $this->createRule();

        self::assertNull($rule->getId());
        self::assertSame('Test Rule', $rule->getName());
        self::assertSame(AlertRuleType::Keyword, $rule->getType());
        self::assertSame([], $rule->getKeywords());
        self::assertNull($rule->getContextPrompt());
        self::assertSame(AlertUrgency::Medium, $rule->getUrgency());
        self::assertSame(5, $rule->getSeverityThreshold());
        self::assertSame(60, $rule->getCooldownMinutes());
        self::assertTrue($rule->isEnabled());
    }

    public function testRequiresAiEvaluationForAiType(): void
    {
        self::assertTrue($this->createRule(AlertRuleType::Ai)->requiresAiEvaluation());
        self::assertTrue($this->createRule(AlertRuleType::Both)->requiresAiEvaluation());
        self::assertFalse($this->createRule(AlertRuleType::Keyword)->requiresAiEvaluation());
    }

    public function testSetKeywords(): void
    {
        $rule = $this->createRule();
        $rule->setKeywords(['breaking', 'urgent']);

        self::assertSame(['breaking', 'urgent'], $rule->getKeywords());
    }

    public function testSetCategories(): void
    {
        $rule = $this->createRule();
        $rule->setCategories(['tech', 'science']);

        self::assertSame(['tech', 'science'], $rule->getCategories());
    }

    private function createRule(AlertRuleType $type = AlertRuleType::Keyword): AlertRule
    {
        $user = new User('admin@example.com', 'hashed');

        return new AlertRule('Test Rule', $type, $user, new \DateTimeImmutable('2026-04-04 12:00:00'));
    }
}
