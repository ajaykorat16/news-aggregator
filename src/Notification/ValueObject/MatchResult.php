<?php

declare(strict_types=1);

namespace App\Notification\ValueObject;

use App\Notification\Entity\AlertRule;

final readonly class MatchResult
{
    /**
     * @param list<string> $matchedKeywords
     */
    public function __construct(
        public AlertRule $alertRule,
        public array $matchedKeywords,
    ) {
    }
}
