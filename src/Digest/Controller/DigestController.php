<?php

declare(strict_types=1);

namespace App\Digest\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DigestController extends AbstractController
{
    #[Route('/digests', name: 'app_digests')]
    public function index(): Response
    {
        return $this->render('digest/index.html.twig');
    }
}
