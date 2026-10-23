<?php

declare(strict_types=1);

/**
 * Derafu: Query - Expressive Path-Based Query Builder for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsQuery\Integration\Bridge\DoctrineORM\Entity\Association;

use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'association_teams')]
class Team
{
    #[ORM\Id]
    #[ORM\Column(length: 255)]
    public string $teamKey;

    #[ORM\Column(length: 255)]
    public string $status;

    #[ORM\Column(type: 'integer')]
    public int $score;

    #[ORM\Column(type: 'decimal', precision: 30, scale: 2, nullable: true)]
    public ?string $amount = null;

    #[ORM\Column(length: 255, nullable: true)]
    public ?string $code = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    public ?DateTimeImmutable $recordedOn = null;

    /** @var Collection<int, Account> */
    #[ORM\ManyToMany(targetEntity: Account::class, mappedBy: 'teams')]
    public Collection $members;

    public function __construct()
    {
        $this->members = new ArrayCollection();
    }
}
