<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PageText;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PageText>
 */
class PageTextRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PageText::class);
    }

    public function findOneByKey(string $textKey): ?PageText
    {
        return $this->findOneBy(['textKey' => $textKey]);
    }

    /**
     * All changed texts, keyed by translation key.
     *
     * @return array<string, PageText>
     */
    public function allByKey(): array
    {
        $texts = [];
        foreach ($this->findAll() as $text) {
            $texts[$text->getTextKey()] = $text;
        }

        return $texts;
    }
}
