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
#[ORM\Table(name: 'invoice_details')]
class InvoiceDetail
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $quantity = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $price = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $discount = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $total = null;

    #[ORM\ManyToOne(targetEntity: Invoice::class, inversedBy: 'invoice_details')]
    #[ORM\JoinColumn(name: 'invoice_id', referencedColumnName: 'id', nullable: true)]
    private ?Invoice $invoice = null;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'invoice_details')]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id', nullable: true)]
    private ?Product $product = null;
}
