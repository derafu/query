<?php

declare(strict_types=1);

/**
 * Derafu: Query - Expressive Path-Based Query Builder for PHP.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

return [
    'products' => [
        ['id' => 1, 'code' => '00123', 'name' => 'Laptop', 'price' => '100.25', 'category' => 'computing', 'flags' => 1, 'created_at' => '2026-10-01'],
        ['id' => 2, 'code' => '123', 'name' => 'Mouse', 'price' => '20.50', 'category' => 'computing', 'flags' => 2, 'created_at' => '2026-10-02'],
        ['id' => 3, 'code' => 'PEN', 'name' => 'Pen', 'price' => '5', 'category' => 'office', 'flags' => 3, 'created_at' => '2026-10-03'],
        ['id' => 4, 'code' => 'SAFE', 'name' => 'O\'Reilly, 100%_safe', 'price' => '0.75', 'category' => 'office', 'flags' => 0, 'created_at' => '2026-10-04'],
        ['id' => 5, 'code' => 'SQL', 'name' => 'x\' OR 1=1 --', 'price' => null, 'category' => null, 'flags' => null, 'created_at' => null],
    ],
    'customers' => [
        ['id' => 1, 'name' => 'Active customer', 'status' => 'active'],
        ['id' => 2, 'name' => 'Inactive customer', 'status' => 'inactive'],
        ['id' => 3, 'name' => 'Customer with null totals', 'status' => 'active'],
        ['id' => 4, 'name' => 'Customer without invoices', 'status' => null],
    ],
    'invoices' => [
        ['id' => 10, 'customer_id' => 1, 'total' => '100.25', 'status' => 'paid'],
        ['id' => 11, 'customer_id' => 1, 'total' => '20.50', 'status' => 'pending'],
        ['id' => 12, 'customer_id' => 2, 'total' => '5', 'status' => 'paid'],
        ['id' => 13, 'customer_id' => 3, 'total' => null, 'status' => 'pending'],
        ['id' => 14, 'customer_id' => 3, 'total' => '0', 'status' => 'pending'],
    ],
    'invoice_details' => [
        ['id' => 100, 'invoice_id' => 10, 'product_id' => 1, 'quantity' => 1],
        ['id' => 101, 'invoice_id' => 11, 'product_id' => 2, 'quantity' => 2],
        ['id' => 102, 'invoice_id' => 12, 'product_id' => 3, 'quantity' => 3],
        ['id' => 103, 'invoice_id' => 10, 'product_id' => 2, 'quantity' => 1],
    ],
    'payments' => [
        ['id' => 1000, 'invoice_id' => 10, 'amount' => '100.25', 'status' => 'completed'],
        ['id' => 1001, 'invoice_id' => 11, 'amount' => '10', 'status' => 'pending'],
        ['id' => 1002, 'invoice_id' => 11, 'amount' => '10.50', 'status' => 'completed'],
    ],
    'association_teams' => [
        ['teamKey' => 't', 'status' => 'active', 'score' => 10, 'amount' => '1.25', 'code' => '00123', 'recordedOn' => '2026-10-01'],
        ['teamKey' => 'u', 'status' => 'inactive', 'score' => 20, 'amount' => '2.50', 'code' => '123', 'recordedOn' => '2026-10-02'],
        ['teamKey' => 'v', 'status' => 'active', 'score' => 30, 'amount' => '999', 'code' => 'OTHER', 'recordedOn' => '2026-10-03'],
    ],
    'association_accounts' => [
        ['accountKey' => 'a', 'status' => 'active', 'primary_team' => 't'],
        ['accountKey' => 'b', 'status' => 'inactive', 'primary_team' => 'u'],
        ['accountKey' => 'c', 'status' => 'active', 'primary_team' => null],
    ],
    'association_profiles' => [
        ['profileKey' => 'p', 'status' => 'active', 'account_key' => 'a'],
        ['profileKey' => 'q', 'status' => 'inactive', 'account_key' => 'b'],
        ['profileKey' => 'r', 'status' => 'active', 'account_key' => null],
    ],
    'association_memberships' => [
        ['account_key' => 'a', 'team_key' => 't'],
        ['account_key' => 'a', 'team_key' => 'u'],
        ['account_key' => 'b', 'team_key' => 'u'],
    ],
    'association_follows' => [
        ['account_key' => 'a', 'team_key' => 't'],
        ['account_key' => 'b', 'team_key' => 'u'],
    ],
    'association_regional_accounts' => [
        ['region' => 'eu', 'code' => 'same', 'primary_team' => 't'],
        ['region' => 'us', 'code' => 'same', 'primary_team' => 'u'],
    ],
    'association_enrollments' => [
        ['account_key' => 'a', 'sequence' => 1, 'primary_team' => 't'],
        ['account_key' => 'a', 'sequence' => 2, 'primary_team' => 'u'],
        ['account_key' => 'b', 'sequence' => 1, 'primary_team' => 'u'],
    ],
];
