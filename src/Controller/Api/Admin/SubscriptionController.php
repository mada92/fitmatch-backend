<?php

namespace App\Controller\Api\Admin;

use App\Entity\Subscription;
use App\Repository\SubscriptionRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/subscriptions')]
#[IsGranted('ROLE_ADMIN')]
class SubscriptionController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private SerializerInterface $serializer;
    private ValidatorInterface $validator;
    private SubscriptionRepository $subscriptionRepository;
    private UserRepository $userRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        SubscriptionRepository $subscriptionRepository,
        UserRepository $userRepository
    ) {
        $this->entityManager = $entityManager;
        $this->serializer = $serializer;
        $this->validator = $validator;
        $this->subscriptionRepository = $subscriptionRepository;
        $this->userRepository = $userRepository;
    }

    #[Route('', name: 'api_admin_subscription_index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = max(1, min(50, $request->query->getInt('limit', 10)));
        $status = $request->query->get('status');
        $type = $request->query->get('type');

        $result = $this->subscriptionRepository->findByFiltersPaginated($status, $type, $page, $limit);

        return $this->json([
            'subscriptions' => $result['data'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($result['total'] / $limit)
        ], Response::HTTP_OK, [], [
            AbstractNormalizer::GROUPS => ['subscription:read', 'subscription:list', 'user:read']
        ]);
    }

    #[Route('/{id}', name: 'api_admin_subscription_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $subscription = $this->subscriptionRepository->find($id);

        if (!$subscription) {
            return $this->json(['message' => 'Subscription not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($subscription, Response::HTTP_OK, [], [
            AbstractNormalizer::GROUPS => ['subscription:read', 'subscription:detail', 'user:read']
        ]);
    }

    #[Route('', name: 'api_admin_subscription_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['userId']) || !isset($data['type']) || !isset($data['price'])) {
            return $this->json(['message' => 'Missing required fields'], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->userRepository->find($data['userId']);
        if (!$user) {
            return $this->json(['message' => 'User not found'], Response::HTTP_BAD_REQUEST);
        }

        $subscription = new Subscription();
        $subscription->setUser($user)
            ->setType($data['type'])
            ->setPrice($data['price'])
            ->setStatus($data['status'] ?? Subscription::STATUS_ACTIVE)
            ->setAutoRenew($data['autoRenew'] ?? false)
            ->setPaymentReference($data['paymentReference'] ?? null);

        // Set start date (default to now)
        $startDate = isset($data['startDate']) ? new \DateTime($data['startDate']) : new \DateTime();
        $subscription->setStartDate($startDate);

        // Calculate end date based on subscription period
        $endDate = clone $startDate;
        $endDate->modify('+1 month'); // Default to monthly
        if (isset($data['endDate'])) {
            $endDate = new \DateTime($data['endDate']);
        }
        $subscription->setEndDate($endDate);

        $errors = $this->validator->validate($subscription);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }

            return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->persist($subscription);
        $this->entityManager->flush();

        return $this->json($subscription, Response::HTTP_CREATED, [], [
            AbstractNormalizer::GROUPS => ['subscription:read', 'subscription:detail']
        ]);
    }

    #[Route('/{id}', name: 'api_admin_subscription_update', methods: ['PUT', 'PATCH'])]
    public function update(Request $request, int $id): JsonResponse
    {
        $subscription = $this->subscriptionRepository->find($id);

        if (!$subscription) {
            return $this->json(['message' => 'Subscription not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['type'])) {
            $subscription->setType($data['type']);
        }

        if (isset($data['price'])) {
            $subscription->setPrice($data['price']);
        }

        if (isset($data['status'])) {
            $subscription->setStatus($data['status']);
        }

        if (isset($data['autoRenew'])) {
            $subscription->setAutoRenew((bool) $data['autoRenew']);
        }

        if (isset($data['paymentReference'])) {
            $subscription->setPaymentReference($data['paymentReference']);
        }

        if (isset($data['startDate'])) {
            $subscription->setStartDate(new \DateTime($data['startDate']));
        }

        if (isset($data['endDate'])) {
            $subscription->setEndDate(new \DateTime($data['endDate']));
        }

        $errors = $this->validator->validate($subscription);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }

            return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->flush();

        return $this->json($subscription, Response::HTTP_OK, [], [
            AbstractNormalizer::GROUPS => ['subscription:read', 'subscription:detail']
        ]);
    }

    #[Route('/{id}', name: 'api_admin_subscription_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $subscription = $this->subscriptionRepository->find($id);

        if (!$subscription) {
            return $this->json(['message' => 'Subscription not found'], Response::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($subscription);
        $this->entityManager->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/cancel', name: 'api_admin_subscription_cancel', methods: ['PATCH'])]
    public function cancel(int $id): JsonResponse
    {
        $subscription = $this->subscriptionRepository->find($id);

        if (!$subscription) {
            return $this->json(['message' => 'Subscription not found'], Response::HTTP_NOT_FOUND);
        }

        if ($subscription->getStatus() === Subscription::STATUS_CANCELED) {
            return $this->json(['message' => 'Subscription already canceled'], Response::HTTP_BAD_REQUEST);
        }

        $subscription->setStatus(Subscription::STATUS_CANCELED);
        $subscription->setAutoRenew(false);

        $this->entityManager->flush();

        return $this->json(['message' => 'Subscription canceled successfully'], Response::HTTP_OK);
    }

    #[Route('/{id}/extend', name: 'api_admin_subscription_extend', methods: ['PATCH'])]
    public function extend(Request $request, int $id): JsonResponse
    {
        $subscription = $this->subscriptionRepository->find($id);

        if (!$subscription) {
            return $this->json(['message' => 'Subscription not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        $days = $data['days'] ?? 30; // Default to 30 days

        // Extend from current end date or from now if already expired
        $fromDate = $subscription->isExpired() ? new \DateTime() : $subscription->getEndDate();
        $newEndDate = clone $fromDate;
        $newEndDate->modify("+{$days} days");

        $subscription->setEndDate($newEndDate);
        $subscription->setStatus(Subscription::STATUS_ACTIVE);

        $this->entityManager->flush();

        return $this->json([
            'message' => 'Subscription extended successfully',
            'newEndDate' => $newEndDate->format('Y-m-d H:i:s')
        ], Response::HTTP_OK);
    }

    #[Route('/stats', name: 'api_admin_subscription_stats', methods: ['GET'])]
    public function stats(): JsonResponse
    {
        $totalSubscriptions = $this->subscriptionRepository->count([]);
        $activeSubscriptions = $this->subscriptionRepository->count(['status' => Subscription::STATUS_ACTIVE]);
        $expiredSubscriptions = $this->subscriptionRepository->count(['status' => Subscription::STATUS_EXPIRED]);
        $canceledSubscriptions = $this->subscriptionRepository->count(['status' => Subscription::STATUS_CANCELED]);

        $basicSubscriptions = $this->subscriptionRepository->count(['type' => Subscription::TYPE_BASIC]);
        $premiumSubscriptions = $this->subscriptionRepository->count(['type' => Subscription::TYPE_PREMIUM]);
        $proSubscriptions = $this->subscriptionRepository->count(['type' => Subscription::TYPE_PRO]);

        $totalRevenue = $this->subscriptionRepository->getTotalRevenue();
        $monthlyRevenue = $this->subscriptionRepository->getRevenueByMonth();

        return $this->json([
            'totalSubscriptions' => $totalSubscriptions,
            'activeSubscriptions' => $activeSubscriptions,
            'expiredSubscriptions' => $expiredSubscriptions,
            'canceledSubscriptions' => $canceledSubscriptions,
            'byType' => [
                'basic' => $basicSubscriptions,
                'premium' => $premiumSubscriptions,
                'pro' => $proSubscriptions
            ],
            'totalRevenue' => $totalRevenue,
            'monthlyRevenue' => $monthlyRevenue
        ]);
    }
}