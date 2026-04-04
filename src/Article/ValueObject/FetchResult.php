<?php

declare(strict_types=1);

namespace App\Article\ValueObject;

use App\Article\Entity\Article;
use App\Source\Entity\Source;

final readonly class FetchResult
{
    /**
     * @param list<Article> $newArticles
     */
    public function __construct(
        public int $persistedCount,
        public array $newArticles,
        public ?Source $source,
    ) {
    }
}
