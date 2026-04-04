<?php

declare(strict_types=1);

namespace App\Notification\Message;

final readonly class SendNotificationMessage
{
    /**
     * @param list<string> $matchedKeywords
     */
    public function __construct(
        public int $alertRuleId,
        public int $articleId,
        public array $matchedKeywords,
    ) {
    }
}
