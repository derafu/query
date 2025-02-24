<?php

declare(strict_types=1);

/**
 * Derafu: Query - Smart Query Builder.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

return [
    'cases' => [
        'find_active_customers' => [
            'description' => 'Find all active customers',
            'sql' => 'SELECT * FROM customers WHERE status = :status',
            'parameters' => ['status' => 'active'],
            'filter' => 'status=active'
        ],
        'expensive_products' => [
            'description' => 'Find products over $1000',
            'sql' => 'SELECT * FROM products WHERE price > :price',
            'parameters' => ['price' => '1000'],
            'filter' => 'price>1000'
        ],
        'recent_payments' => [
            'description' => 'Find payments from March 2024',
            'sql' => 'SELECT * FROM payments WHERE DATE(date) >= :start AND DATE(date) < :end',
            'parameters' => [
                'start' => '2024-03-01',
                'end' => '2024-04-01'
            ],
            'filter' => 'date:20240301'
        ]
    ]
];
