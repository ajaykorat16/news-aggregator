<?php

declare(strict_types=1);

namespace App\Notification\ValueObject;

enum AlertRuleType: string
{
    case Keyword = 'keyword';
    case Ai = 'ai';
    case Both = 'both';
}
