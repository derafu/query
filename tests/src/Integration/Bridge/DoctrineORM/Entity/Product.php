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

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'products')]
class Product
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $code = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $name = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $price = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $category = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $flags = null;

    #[ORM\Column(name: 'created_at', type: 'string', nullable: true)]
    private ?string $created_at = null;

    #[ORM\Column(name: 'deleted_at', type: 'string', nullable: true)]
    private ?string $deleted_at = null;

    #[ORM\OneToMany(targetEntity: InvoiceDetail::class, mappedBy: 'product')]
    private Collection $invoice_details;

    public function __construct()
    {
        $this->invoice_details = new ArrayCollection();
    }
}
