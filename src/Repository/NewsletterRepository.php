<?php

namespace App\Repository;

use App\Entity\Newsletter;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Newsletter>
 */
class NewsletterRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Newsletter::class);
    }

    /**
     * Zapisuje nowego subskrybenta newslettera
     */
    public function save(Newsletter $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Sprawdza czy adres email już istnieje w bazie
     */
    public function emailExists(string $email): bool
    {
        $result = $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.email = :email')
            ->setParameter('email', mb_strtolower($email))
            ->getQuery()
            ->getSingleScalarResult();

        return $result > 0;
    }
}