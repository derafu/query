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
#[ORM\Table(name: 'association_regional_accounts')]
class RegionalAccount
{
    #[ORM\Id]
    #[ORM\Column(length: 255)]
    public string $region;

    #[ORM\Id]
    #[ORM\Column(length: 255)]
    public string $code;

    #[ORM\ManyToOne(targetEntity: Team::class)]
    #[ORM\JoinColumn(name: 'primary_team', referencedColumnName: 'teamKey')]
    public ?Team $primaryTeam = null;
}
