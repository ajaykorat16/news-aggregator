<?php

declare(strict_types=1);

namespace App\Notification\ValueObject;

final readonly class EvaluationResult
{
    public function __construct(
        public int $severity,
        public string $explanation,
        public ?string $modelUsed = null,
    ) {
    }
}
