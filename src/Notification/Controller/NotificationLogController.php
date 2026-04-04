<?php

declare(strict_types=1);

namespace App\Notification\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class NotificationLogController extends AbstractController
{
    #[Route('/notifications', name: 'app_notifications')]
    public function index(): Response
    {
        return $this->render('notification/index.html.twig');
    }
}
