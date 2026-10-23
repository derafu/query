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

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'association_accounts')]
class Account
{
    #[ORM\Id]
    #[ORM\Column(length: 255)]
    public string $accountKey;

    #[ORM\Column(length: 255)]
    public string $status;

    #[ORM\ManyToOne(targetEntity: Team::class)]
    #[ORM\JoinColumn(name: 'primary_team', referencedColumnName: 'teamKey')]
    public ?Team $primaryTeam = null;

    #[ORM\OneToOne(targetEntity: AccountProfile::class, mappedBy: 'account')]
    public ?AccountProfile $profile = null;

    /** @var Collection<int, Team> */
    #[ORM\ManyToMany(targetEntity: Team::class, inversedBy: 'members')]
    #[ORM\JoinTable(name: 'association_memberships')]
    #[ORM\JoinColumn(name: 'account_key', referencedColumnName: 'accountKey')]
    #[ORM\InverseJoinColumn(name: 'team_key', referencedColumnName: 'teamKey')]
    public Collection $teams;

    /** @var Collection<int, Team> */
    #[ORM\ManyToMany(targetEntity: Team::class)]
    #[ORM\JoinTable(name: 'association_follows')]
    #[ORM\JoinColumn(name: 'account_key', referencedColumnName: 'accountKey')]
    #[ORM\InverseJoinColumn(name: 'team_key', referencedColumnName: 'teamKey')]
    public Collection $followedTeams;

    public function __construct()
    {
        $this->teams = new ArrayCollection();
        $this->followedTeams = new ArrayCollection();
    }
}
