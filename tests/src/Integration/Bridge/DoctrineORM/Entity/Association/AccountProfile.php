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
#[ORM\Table(name: 'association_profiles')]
class AccountProfile
{
    #[ORM\Id]
    #[ORM\Column(length: 255)]
    public string $profileKey;

    #[ORM\Column(length: 255)]
    public string $status;

    #[ORM\OneToOne(targetEntity: Account::class, inversedBy: 'profile')]
    #[ORM\JoinColumn(name: 'account_key', referencedColumnName: 'accountKey')]
    public ?Account $account = null;
}
