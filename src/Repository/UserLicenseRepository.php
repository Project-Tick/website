<?php

namespace App\Repository;

use App\Entity\UserLicense;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserLicense>
 *
 * @method UserLicense|null find($id, $lockMode = null, $lockVersion = null)
 * @method UserLicense|null findOneBy(array $criteria, array $orderBy = null)
 * @method UserLicense[]    findAll()
 * @method UserLicense[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserLicenseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserLicense::class);
    }

    public function add(UserLicense $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(UserLicense $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
