<?php

declare(strict_types=1);

namespace App\Shared\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AiStatsController extends AbstractController
{
    #[Route('/stats/ai', name: 'app_ai_stats')]
    public function index(): Response
    {
        return $this->render('stats/ai.html.twig');
    }
}
