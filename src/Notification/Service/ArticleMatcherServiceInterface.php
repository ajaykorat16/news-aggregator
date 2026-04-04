<?php

declare(strict_types=1);

namespace App\Notification\Service;

use App\Article\Entity\Article;
use App\Notification\ValueObject\MatchResult;

interface ArticleMatcherServiceInterface
{
    /**
     * @return list<MatchResult>
     */
    public function match(Article $article): array;
}
