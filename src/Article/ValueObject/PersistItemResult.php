<?php

declare(strict_types=1);

namespace App\Article\ValueObject;

use App\Article\Entity\Article;
use App\Source\Entity\Source;

final readonly class PersistItemResult
{
    public function __construct(
        public ?Article $article,
        public Source $source,
    ) {
    }
}
