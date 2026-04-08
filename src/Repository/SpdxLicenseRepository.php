<?php

namespace App\Repository;

use App\Entity\SpdxLicense;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SpdxLicense>
 *
 * @method SpdxLicense|null find($id, $lockMode = null, $lockVersion = null)
 * @method SpdxLicense|null findOneBy(array $criteria, array $orderBy = null)
 * @method SpdxLicense[]    findAll()
 * @method SpdxLicense[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SpdxLicenseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SpdxLicense::class);
    }
}
