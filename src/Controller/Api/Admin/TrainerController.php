<?php

namespace App\Controller\Api\Admin;

use App\Entity\Trainer;
use App\Entity\User;
use App\Repository\TrainerRepository;
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

#[Route('/api/admin/trainers')]
#[IsGranted('ROLE_ADMIN')]
class TrainerController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private SerializerInterface $serializer;
    private ValidatorInterface $validator;
    private TrainerRepository $trainerRepository;
    private UserRepository $userRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        TrainerRepository $trainerRepository,
        UserRepository $userRepository
    ) {
        $this->entityManager = $entityManager;
        $this->serializer = $serializer;
        $this->validator = $validator;
        $this->trainerRepository = $trainerRepository;
        $this->userRepository = $userRepository;
    }

    #[Route('', name: 'api_admin_trainer_index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = max(1, min(50, $request->query->getInt('limit', 10)));
        $search = $request->query->get('search', '');
        $status = $request->query->get('status', null);

        $result = $this->trainerRepository->findBySearchAndStatusPaginated($search, $status, $page, $limit);

        return $this->json([
            'trainers' => $result['data'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($result['total'] / $limit)
        ], Response::HTTP_OK, [], [
            AbstractNormalizer::GROUPS => ['trainer:read', 'trainer:list', 'user:read']
        ]);
    }

    #[Route('/{id}', name: 'api_admin_trainer_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $trainer = $this->trainerRepository->find($id);

        if (!$trainer) {
            return $this->json(['message' => 'Trainer not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($trainer, Response::HTTP_OK, [], [
            AbstractNormalizer::GROUPS => ['trainer:read', 'trainer:detail', 'user:read']
        ]);
    }

    #[Route('/{id}/approve', name: 'api_admin_trainer_approve', methods: ['PATCH'])]
    public function approve(int $id): JsonResponse
    {
        $trainer = $this->trainerRepository->find($id);

        if (!$trainer) {
            return $this->json(['message' => 'Trainer not found'], Response::HTTP_NOT_FOUND);
        }

        if ($trainer->getStatus() === Trainer::STATUS_APPROVED) {
            return $this->json(['message' => 'Trainer already approved'], Response::HTTP_BAD_REQUEST);
        }

        $trainer->setStatus(Trainer::STATUS_APPROVED);
        $trainer->setIsVerified(true);

        // Add trainer role to user if not present
        $user = $trainer->getUser();
        if ($user && !$user->hasRole(User::ROLE_TRAINER)) {
            $user->addRole(User::ROLE_TRAINER);
        }

        $this->entityManager->flush();

        return $this->json(['message' => 'Trainer approved successfully'], Response::HTTP_OK);
    }

    #[Route('/{id}/reject', name: 'api_admin_trainer_reject', methods: ['PATCH'])]
    public function reject(int $id, Request $request): JsonResponse
    {
        $trainer = $this->trainerRepository->find($id);

        if (!$trainer) {
            return $this->json(['message' => 'Trainer not found'], Response::HTTP_NOT_FOUND);
        }

        if ($trainer->getStatus() === Trainer::STATUS_REJECTED) {
            return $this->json(['message' => 'Trainer already rejected'], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true);
        $rejectionReason = $data['reason'] ?? null;

        $trainer->setStatus(Trainer::STATUS_REJECTED);
        // Store rejection reason somewhere (could add a column to Trainer entity)

        $this->entityManager->flush();

        return $this->json(['message' => 'Trainer rejected successfully'], Response::HTTP_OK);
    }

    #[Route('/{id}', name: 'api_admin_trainer_update', methods: ['PUT', 'PATCH'])]
    public function update(Request $request, int $id): JsonResponse
    {
        $trainer = $this->trainerRepository->find($id);

        if (!$trainer) {
            return $this->json(['message' => 'Trainer not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        // Update trainer details
        if (isset($data['bio'])) {
            $trainer->setBio($data['bio']);
        }

        if (isset($data['title'])) {
            $trainer->setTitle($data['title']);
        }

        if (isset($data['city'])) {
            $trainer->setCity($data['city']);
        }

        if (isset($data['zipCode'])) {
            $trainer->setZipCode($data['zipCode']);
        }

        if (isset($data['address'])) {
            $trainer->setAddress($data['address']);
        }

        if (isset($data['hourlyRate'])) {
            $trainer->setHourlyRate($data['hourlyRate']);
        }

        if (isset($data['website'])) {
            $trainer->setWebsite($data['website']);
        }

        if (isset($data['specializations'])) {
            $trainer->setSpecializations($data['specializations']);
        }

        if (isset($data['certificates'])) {
            $trainer->setCertificates($data['certificates']);
        }

        if (isset($data['socialProfiles'])) {
            $trainer->setSocialProfiles($data['socialProfiles']);
        }

        // Status can only be changed via the specific approval/rejection endpoints

        $errors = $this->validator->validate($trainer);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }

            return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->flush();

        return $this->json($trainer, Response::HTTP_OK, [], [
            AbstractNormalizer::GROUPS => ['trainer:read', 'trainer:detail']
        ]);
    }

    #[Route('/{id}', name: 'api_admin_trainer_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $trainer = $this->trainerRepository->find($id);

        if (!$trainer) {
            return $this->json(['message' => 'Trainer not found'], Response::HTTP_NOT_FOUND);
        }

        $user = $trainer->getUser();
        if ($user && $user->hasRole(User::ROLE_TRAINER)) {
            $user->removeRole(User::ROLE_TRAINER);
        }

        $this->entityManager->remove($trainer);
        $this->entityManager->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/pending-approval', name: 'api_admin_trainer_pending', methods: ['GET'])]
    public function pendingApproval(): JsonResponse
    {
        $trainers = $this->trainerRepository->findBy(['status' => Trainer::STATUS_PENDING]);

        return $this->json([
            'trainers' => $trainers,
            'count' => count($trainers)
        ], Response::HTTP_OK, [], [
            AbstractNormalizer::GROUPS => ['trainer:read', 'trainer:list', 'user:read']
        ]);
    }

    #[Route('/statistics', name: 'api_admin_trainer_stats', methods: ['GET'])]
    public function statistics(): JsonResponse
    {
        $total = $this->trainerRepository->count([]);
        $approved = $this->trainerRepository->count(['status' => Trainer::STATUS_APPROVED]);
        $pending = $this->trainerRepository->count(['status' => Trainer::STATUS_PENDING]);
        $rejected = $this->trainerRepository->count(['status' => Trainer::STATUS_REJECTED]);

        $cityCounts = $this->trainerRepository->getTrainerCountsByCity();
        $specializationCounts = $this->trainerRepository->getTrainerCountsBySpecialization();

        return $this->json([
            'total' => $total,
            'approved' => $approved,
            'pending' => $pending,
            'rejected' => $rejected,
            'byCities' => $cityCounts,
            'bySpecializations' => $specializationCounts
        ]);
    }
}