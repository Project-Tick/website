<?php

namespace App\Twig;

use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class GlobalProductExtension extends AbstractExtension
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_all_products', [$this, 'getAllProducts']),
        ];
    }

    public function getAllProducts(): array
    {
        return $this->entityManager->getRepository(Product::class)->findBy([], ['title' => 'ASC']);
    }
}
