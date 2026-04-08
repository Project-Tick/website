<?php

namespace App\Repository;

use App\Entity\News;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<News>
 *
 * @method News|null find($id, $lockMode = null, $lockVersion = null)
 * @method News|null findOneBy(array $criteria, array $orderBy = null)
 * @method News[]    findAll()
 * @method News[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NewsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, News::class);
    }

    /**
     * @return News[]
     */
    public function search(string $query, ?string $categorySlug = null): array
    {
        $qb = $this->createQueryBuilder('n')
            ->where('n.title LIKE :query OR n.content LIKE :query OR n.description LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('n.date', 'DESC');

        if ($categorySlug) {
            $qb->join('n.category', 'c')
               ->andWhere('c.slug = :categorySlug')
               ->setParameter('categorySlug', $categorySlug);
        }

        return $qb->getQuery()->getResult();
    }
}
