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

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'association_enrollments')]
class Enrollment
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(name: 'account_key', referencedColumnName: 'accountKey')]
    public Account $account;

    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    public int $sequence;

    #[ORM\ManyToOne(targetEntity: Team::class)]
    #[ORM\JoinColumn(name: 'primary_team', referencedColumnName: 'teamKey')]
    public ?Team $primaryTeam = null;
}
