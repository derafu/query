<?php

declare(strict_types=1);

/**
 * Derafu: Query - Expressive Path-Based Query Builder for PHP.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsQuery\Integration\Bridge\DoctrineORM\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'payments')]
class Payment
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $date = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $amount = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $method = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $status = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $reference = null;

    #[ORM\Column(name: 'created_at', type: 'string', nullable: true)]
    private ?string $created_at = null;

    #[ORM\ManyToOne(targetEntity: Invoice::class, inversedBy: 'payments')]
    #[ORM\JoinColumn(name: 'invoice_id', referencedColumnName: 'id', nullable: true)]
    private ?Invoice $invoice = null;
}
