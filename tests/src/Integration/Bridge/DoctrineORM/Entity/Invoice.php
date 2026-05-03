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
#[ORM\Table(name: 'invoices')]
class Invoice
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $number = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $date = null;

    #[ORM\Column(name: 'due_date', type: 'string', nullable: true)]
    private ?string $due_date = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $status = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $total = null;

    #[ORM\Column(name: 'created_at', type: 'string', nullable: true)]
    private ?string $created_at = null;

    #[ORM\Column(name: 'deleted_at', type: 'string', nullable: true)]
    private ?string $deleted_at = null;

    #[ORM\ManyToOne(targetEntity: Customer::class, inversedBy: 'invoices')]
    #[ORM\JoinColumn(name: 'customer_id', referencedColumnName: 'id', nullable: true)]
    private ?Customer $customer = null;

    #[ORM\OneToMany(targetEntity: InvoiceDetail::class, mappedBy: 'invoice')]
    private Collection $invoice_details;

    #[ORM\OneToMany(targetEntity: Payment::class, mappedBy: 'invoice')]
    private Collection $payments;

    public function __construct()
    {
        $this->invoice_details = new ArrayCollection();
        $this->payments = new ArrayCollection();
    }
}
