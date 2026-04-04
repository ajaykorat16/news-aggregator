<?php

declare(strict_types=1);

namespace App\Notification\ValueObject;

enum AlertUrgency: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
}
