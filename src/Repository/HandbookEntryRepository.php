<?php

namespace App\Repository;

use App\Entity\HandbookEntry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HandbookEntry>
 */
class HandbookEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HandbookEntry::class);
    }

    /**
     * @return HandbookEntry[]
     */
    public function search(string $query): array
    {
        return $this->createQueryBuilder('h')
            ->where('h.title LIKE :query')
            ->orWhere('h.content LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('h.title', 'ASC')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();
    }
}
