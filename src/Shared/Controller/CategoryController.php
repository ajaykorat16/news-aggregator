<?php

declare(strict_types=1);

namespace App\Shared\Controller;

use App\Shared\Repository\CategoryRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CategoryController
{
    public function __construct(
        private readonly ControllerHelper $controller,
        private readonly CategoryRepositoryInterface $categoryRepository,
    ) {
    }

    #[Route('/categories', name: 'app_categories')]
    public function __invoke(): Response
    {
        return $this->controller->render('category/index.html.twig', [
            'categories' => $this->categoryRepository->findAllOrderedByWeight(),
        ]);
    }
}
