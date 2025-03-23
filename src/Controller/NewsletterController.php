<?php

namespace App\Controller;

use App\Service\NewsletterService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class NewsletterController extends AbstractController
{
    #[Route('/newsletter/subscribe', name: 'newsletter_subscribe', methods: ['POST'])]
    public function subscribe(
        Request $request,
        NewsletterService $newsletterService
    ): JsonResponse {
        // Pobranie adresu email z żądania
        $data = json_decode($request->getContent(), true) ?? [];
        $email = $data['email'] ?? $request->request->get('email');

        if (!$email) {
            return $this->json([
                'success' => false,
                'message' => 'Brak adresu email.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Próba dodania subskrybenta
        $result = $newsletterService->addSubscriber($email, $request->getClientIp());

        // Obsługa odpowiedzi
        if ($result['success']) {
            return $this->json([
                'success' => true,
                'message' => $result['message']
            ]);
        }

        // Zwróć błąd z odpowiednim kodem HTTP
        $statusCode = Response::HTTP_BAD_REQUEST;
        if (isset($result['exception'])) {
            $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR;

            // W trybie deweloperskim dodajemy szczegóły błędu
            if ($this->getParameter('kernel.environment') === 'dev') {
                $result['dev_error'] = $result['exception'];
            }
            unset($result['exception']);
        }

        return $this->json($result, $statusCode);
    }

    /**
     * Endpoint do sprawdzania stanu subskrypcji newslettera
     * Ten endpoint może być użyty np. przez panel administracyjny
     */
    #[Route('/newsletter/check', name: 'newsletter_check_status', methods: ['GET'])]
    public function checkStatus(Request $request): JsonResponse
    {
        // Tylko w środowisku deweloperskim
        if ($this->getParameter('kernel.environment') !== 'dev') {
            throw $this->createNotFoundException('Endpoint dostępny tylko w środowisku deweloperskim');
        }

        return $this->json([
            'status' => 'OK',
            'message' => 'System subskrypcji newslettera działa prawidłowo',
            'timestamp' => new \DateTime()
        ]);
    }
}