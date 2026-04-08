<?php

namespace App\Repository;

use App\Entity\SiteSetting;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SiteSetting>
 */
class SiteSettingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SiteSetting::class);
    }

    public function getValue(string $key, string $default = ''): string
    {
        $setting = $this->findOneBy(['settingKey' => $key]);
        $value = $setting ? $setting->getSettingValue() : null;
        
        return ($value !== null && $value !== '') ? $value : $default;
    }
}
