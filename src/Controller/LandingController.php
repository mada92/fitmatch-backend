<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class LandingController extends AbstractController
{
    #[Route('/', name: 'landing_page')]
    public function index(): Response
    {
        // Tutaj możemy po prostu zwrócić statyczny HTML jako odpowiedź lub przekierować do statycznej strony
        return $this->render('landing/index.html.twig');
    }

    #[Route('/newsletter/subscribe', name: 'newsletter_subscribe')]
    public function subscribeToNewsletter(): Response
    {
        // To jest przykładowy endpoint do obsługi zapisów do newslettera
        // W przyszłości będziemy tutaj obsługiwać logikę zapisu do bazy danych

        // Dla teraz po prostu zwracamy odpowiedź JSON
        return $this->json([
            'success' => true,
            'message' => 'Dziękujemy za zapisanie się do newslettera!'
        ]);
    }
}