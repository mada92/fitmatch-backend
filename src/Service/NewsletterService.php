<?php

namespace App\Service;

use App\Entity\Newsletter;
use App\Repository\NewsletterRepository;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class NewsletterService
{
    public function __construct(
        private NewsletterRepository $newsletterRepository,
        private ValidatorInterface $validator
    ) {
    }

    /**
     * Dodaje nowego subskrybenta do newslettera
     *
     * @param string $email Adres email subskrybenta
     * @param string|null $ipAddress Adres IP (opcjonalny)
     * @return array Rezultat operacji ['success' => bool, 'message' => string, 'errors' => array]
     */
    public function addSubscriber(string $email, ?string $ipAddress = null): array
    {
        // Normalizacja emaila (małe litery, usunięcie białych znaków)
        $email = trim(mb_strtolower($email));

        // Sprawdzenie czy email już istnieje
        if ($this->newsletterRepository->emailExists($email)) {
            // Zwracamy sukces, żeby nie ujawniać, że email jest już w bazie
            return [
                'success' => true,
                'message' => 'Dziękujemy za zapisanie się do newslettera!'
            ];
        }

        // Tworzenie i walidacja nowego subskrybenta
        $subscriber = new Newsletter();
        $subscriber->setEmail($email);

        if ($ipAddress) {
            $subscriber->setIpAddress($ipAddress);
        }

        $errors = $this->validator->validate($subscriber);

        // Jeśli są błędy walidacji
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }

            return [
                'success' => false,
                'message' => 'Nieprawidłowy adres email.',
                'errors' => $errorMessages
            ];
        }

        // Zapisanie subskrybenta
        try {
            $this->newsletterRepository->save($subscriber);

            return [
                'success' => true,
                'message' => 'Dziękujemy za zapisanie się do newslettera!'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Wystąpił błąd podczas zapisywania. Spróbuj ponownie później.',
                'exception' => $e->getMessage()
            ];
        }
    }
}