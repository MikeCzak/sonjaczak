<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Settings;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Settings>
 */
class SettingsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Settings::class);
    }

    /**
     * The one row, or new defaults (not persisted) as long as Sonja has not saved the settings yet.
     *
     * No caching in this service: FrankenPHP runs in worker mode, a service lives across requests, and a remembered
     * value would stay old after Sonja changes it. Doctrine's own identity map is cleared after every request.
     */
    public function current(): Settings
    {
        return $this->find(Settings::ID) ?? new Settings();
    }
}
