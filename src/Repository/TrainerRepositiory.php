<?php

namespace App\Repository;

use App\Entity\Trainer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Trainer>
 *
 * @method Trainer|null find($id, $lockMode = null, $lockVersion = null)
 * @method Trainer|null findOneBy(array $criteria, array $orderBy = null)
 * @method Trainer[]    findAll()
 * @method Trainer[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TrainerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Trainer::class);
    }

    /**
     * Find trainers with search filtering, status filtering and pagination
     */
    public function findBySearchAndStatusPaginated(
        string $search = '',
        ?string $status = null,
        int $page = 1,
        int $limit = 10
    ): array {
        $qb = $this->createQueryBuilder('t')
            ->select('t', 'u')
            ->leftJoin('t.user', 'u');

        if ($search) {
            $qb->andWhere('u.firstName LIKE :search OR u.lastName LIKE :search OR t.bio LIKE :search OR t.city LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($status !== null) {
            $qb->andWhere('t.status = :status')
                ->setParameter('status', $status);
        }

        $qb->orderBy('t.id', 'DESC');

        // Get total results count for pagination
        $countQb = clone $qb;
        $total = $countQb->select('COUNT(t.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // Add pagination
        $offset = ($page - 1) * $limit;
        $qb->setFirstResult($offset)
            ->setMaxResults($limit);

        $trainers = $qb->getQuery()->getResult();

        return [
            'data' => $trainers,
            'total' => $total
        ];
    }

    /**
     * Find trainers by city
     */
    public function findByCity(string $city): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.city = :city')
            ->andWhere('t.status = :status')
            ->setParameter('city', $city)
            ->setParameter('status', Trainer::STATUS_APPROVED)
            ->orderBy('t.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find trainers by specialization
     */
    public function findBySpecialization(string $specialization): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.specializations LIKE :spec')
            ->andWhere('t.status = :status')
            ->setParameter('spec', '%' . $specialization . '%')
            ->setParameter('status', Trainer::STATUS_APPROVED)
            ->orderBy('t.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find verified trainers
     */
    public function findVerified(int $limit = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->andWhere('t.isVerified = :verified')
            ->andWhere('t.status = :status')
            ->setParameter('verified', true)
            ->setParameter('status', Trainer::STATUS_APPROVED)
            ->orderBy('t.id', 'DESC');

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find trainers with top ratings
     */
    public function findTopRated(int $limit = 10): array
    {
        $entityManager = $this->getEntityManager();

        $query = $entityManager->createQuery(
            'SELECT t, AVG(r.rating) as avgRating
             FROM App\Entity\Trainer t
             JOIN t.reviews r
             WHERE t.status = :status
             GROUP BY t.id
             HAVING COUNT(r.id) > 3
             ORDER BY avgRating DESC'
        )->setParameter('status', Trainer::STATUS_APPROVED)
            ->setMaxResults($limit);

        return $query->getResult();
    }

    /**
     * Get trainer counts by city
     */
    public function getTrainerCountsByCity(): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = "
            SELECT 
                city, 
                COUNT(id) as count
            FROM 
                trainer
            WHERE 
                status = :status
            GROUP BY 
                city
            ORDER BY 
                count DESC
        ";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue('status', Trainer::STATUS_APPROVED);
        $result = $stmt->executeQuery();

        return $result->fetchAllAssociative();
    }

    /**
     * Get trainer counts by specialization
     */
    public function getTrainerCountsBySpecialization(): array
    {
        $results = [];
        $trainers = $this->findBy(['status' => Trainer::STATUS_APPROVED]);

        $specializationCounts = [];

        foreach ($trainers as $trainer) {
            $specializations = $trainer->getSpecializations() ?: [];

            foreach ($specializations as $specialization) {
                if (!isset($specializationCounts[$specialization])) {
                    $specializationCounts[$specialization] = 0;
                }

                $specializationCounts[$specialization]++;
            }
        }

        // Convert to array format for API
        foreach ($specializationCounts as $specialization => $count) {
            $results[] = [
                'specialization' => $specialization,
                'count' => $count
            ];
        }

        // Sort by count descending
        usort($results, function($a, $b) {
            return $b['count'] <=> $a['count'];
        });

        return $results;
    }

    /**
     * Advanced search for trainers based on multiple criteria
     */
    public function advancedSearch(
        array $criteria = [],
        int $page = 1,
        int $limit = 10
    ): array {
        $qb = $this->createQueryBuilder('t')
            ->select('t', 'u')
            ->leftJoin('t.user', 'u')
            ->where('t.status = :status')
            ->setParameter('status', Trainer::STATUS_APPROVED);

        // Search by city
        if (!empty($criteria['city'])) {
            $qb->andWhere('t.city = :city')
                ->setParameter('city', $criteria['city']);
        }

        // Search by keyword (in name, bio)
        if (!empty($criteria['keyword'])) {
            $qb->andWhere('u.firstName LIKE :keyword OR u.lastName LIKE :keyword OR t.bio LIKE :keyword')
                ->setParameter('keyword', '%' . $criteria['keyword'] . '%');
        }

        // Search by specialization
        if (!empty($criteria['specialization'])) {
            $qb->andWhere('t.specializations LIKE :spec')
                ->setParameter('spec', '%' . $criteria['specialization'] . '%');
        }

        // Price range
        if (!empty($criteria['priceMin'])) {
            $qb->andWhere('t.hourlyRate >= :priceMin')
                ->setParameter('priceMin', $criteria['priceMin']);
        }

        if (!empty($criteria['priceMax'])) {
            $qb->andWhere('t.hourlyRate <= :priceMax')
                ->setParameter('priceMax', $criteria['priceMax']);
        }

        // Sort options
        $sortField = !empty($criteria['sortBy']) ? $criteria['sortBy'] : 'id';
        $sortDirection = !empty($criteria['sortDirection']) ? $criteria['sortDirection'] : 'DESC';

        // Only allow sorting by valid fields
        $allowedSortFields = ['hourlyRate', 'city', 'id'];
        if (!in_array($sortField, $allowedSortFields)) {
            $sortField = 'id';
        }

        $qb->orderBy('t.' . $sortField, $sortDirection);

        // Get total results count for pagination
        $countQb = clone $qb;
        $total = $countQb->select('COUNT(t.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // Add pagination
        $offset = ($page - 1) * $limit;
        $qb->setFirstResult($offset)
            ->setMaxResults($limit);

        $trainers = $qb->getQuery()->getResult();

        return [
            'data' => $trainers,
            'total' => $total,
            'pages' => ceil($total / $limit)
        ];
    }

//    /**
//     * @return Trainer[] Returns an array of Trainer objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('t')
//            ->andWhere('t.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('t.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Trainer
//    {
//        return $this->createQueryBuilder('t')
//            ->andWhere('t.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}