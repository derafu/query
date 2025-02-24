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
            'raw_sql' => [
                'sql' => 'SELECT * FROM customers WHERE status = :status',
                'parameters' => ['status' => 'active'],
            ],
            'smart_query' => [
                'table' => 'customers',
                'path' => 'status',
                'filter' => '=active',
            ],
        ],
        'expensive_products' => [
            'description' => 'Find expensive products',
            'raw_sql' => [
                'sql' => 'SELECT * FROM products WHERE price > :price',
                'parameters' => ['price' => '1000'],
            ],
            'smart_query' => [
                'table' => 'products',
                'path' => 'price',
                'filter' => '>1000',
            ],
        ],
        'recent_invoices' => [
            'description' => 'Find March 2024 invoices',
            'raw_sql' => [
                'sql' => 'SELECT * FROM invoices WHERE DATE(date) >= :start AND DATE(date) < :end',
                'parameters' => [
                    'start' => '2024-03-01',
                    'end' => '2024-03-02',
                ],
            ],
            'smart_query' => [
                'table' => 'invoices',
                'path' => 'date',
                'filter' => 'date:20240301',
            ],
        ],

        // Operadores estándar (=, !=, >, <, >=, <=).
        'equal_match' => [
            'description' => 'Find customer by exact tax_id',
            'raw_sql' => [
                'sql' => 'SELECT * FROM customers WHERE tax_id = :value',
                'parameters' => ['value' => '123456789'],
            ],
            'smart_query' => [
                'table' => 'customers',
                'path' => 'tax_id',
                'filter' => '=123456789',
            ],
        ],
        'not_equal_match' => [
            'description' => 'Find non-electronic products',
            'raw_sql' => [
                'sql' => 'SELECT * FROM products WHERE category != :value',
                'parameters' => ['value' => 'electronics'],
            ],
            'smart_query' => [
                'table' => 'products',
                'path' => 'category',
                'filter' => '!=electronics',
            ],
        ],
        'greater_than' => [
            'description' => 'Find expensive products (>1000)',
            'raw_sql' => [
                'sql' => 'SELECT * FROM products WHERE price > :value',
                'parameters' => ['value' => '1000'],
            ],
            'smart_query' => [
                'table' => 'products',
                'path' => 'price',
                'filter' => '>1000',
            ],
        ],

        // LIKE operators.
        'contains_case_sensitive' => [
            'description' => 'Find products with "License" in name',
            'raw_sql' => [
                'sql' => 'SELECT * FROM products WHERE name LIKE :value',
                'parameters' => ['value' => '%License%'],
            ],
            'smart_query' => [
                'table' => 'products',
                'path' => 'name',
                'filter' => '~~License',
            ],
        ],
        'starts_with' => [
            'description' => 'Find products starting with "Tech"',
            'raw_sql' => [
                'sql' => 'SELECT * FROM products WHERE name LIKE :value',
                'parameters' => ['value' => 'Tech%'],
            ],
            'smart_query' => [
                'table' => 'products',
                'path' => 'name',
                'filter' => '^Tech',
            ],
        ],

        // IN operators.
        'status_in_list' => [
            'description' => 'Find invoices in specific states',
            'raw_sql' => [
                'sql' => 'SELECT * FROM invoices WHERE status IN (:value1, :value2)',
                'parameters' => ['value1' => 'paid', 'value2' => 'issued'],
            ],
            'smart_query' => [
                'table' => 'invoices',
                'path' => 'status',
                'filter' => 'in:paid,issued',
            ],
        ],

        // Range operators.
        'price_between' => [
            'description' => 'Find products in price range',
            'raw_sql' => [
                'sql' => 'SELECT * FROM products WHERE price BETWEEN :value1 AND :value2',
                'parameters' => ['value1' => '100', 'value2' => '1000'],
            ],
            'smart_query' => [
                'table' => 'products',
                'path' => 'price',
                'filter' => 'between:100,1000',
            ],
        ],

        // NULL operators.
        'soft_deleted' => [
            'description' => 'Find soft deleted records',
            'raw_sql' => [
                'sql' => 'SELECT * FROM customers WHERE deleted_at IS NOT NULL',
                'parameters' => [],
            ],
            'smart_query' => [
                'table' => 'customers',
                'path' => 'deleted_at',
                'filter' => 'isnot:null',
            ],
        ],

        // Date operators (usando las funciones de SQLite).
        'march_invoices' => [
            'description' => 'Find invoices from March 2024',
            'raw_sql' => [
                'sql' => 'SELECT * FROM invoices WHERE strftime("%Y%m", date) = :value',
                'parameters' => ['value' => '202403'],
            ],
            'smart_query' => [
                'table' => 'invoices',
                'path' => 'date',
                'filter' => 'period:202403',
            ],
        ],

        // Bitwise operators.
        'taxable_products' => [
            'description' => 'Find taxable products (flag 1)',
            'raw_sql' => [
                'sql' => 'SELECT * FROM products WHERE flags & :value',
                'parameters' => ['value' => '1'],
            ],
            'smart_query' => [
                'table' => 'products',
                'path' => 'flags',
                'filter' => 'b&1',
            ],
        ],
    ],
];
