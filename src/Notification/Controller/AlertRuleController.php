<?php

declare(strict_types=1);

namespace App\Notification\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AlertRuleController extends AbstractController
{
    #[Route('/alerts', name: 'app_alerts')]
    public function index(): Response
    {
        return $this->render('alert/index.html.twig');
    }
}
