<?php

declare(strict_types=1);

/**
 * Derafu: Query - Expressive Path-Based Query Builder for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

/**
 * Provides test cases for path parsing with expected results.
 *
 * This file contains an extensive list of valid and invalid path
 * expressions for testing, along with expected segment data.
 */
return [
    // Valid path expressions (OK) with expected results.
    'OK' => [
        // Basic paths
        [
            'expression' => 'author__books__title',
            'expected' => [
                'segments_count' => 3,
                'segment_names' => ['author', 'books', 'title'],
                'segment_options' => [[], [], []],
            ],
        ],
        [
            'expression' => 'invoices__customers__name',
            'expected' => [
                'segments_count' => 3,
                'segment_names' => ['invoices', 'customers', 'name'],
                'segment_options' => [[], [], []],
            ],
        ],

        // Paths with options
        [
            'expression' => 'author__books[alias:b]__title',
            'expected' => [
                'segments_count' => 3,
                'segment_names' => ['author', 'books', 'title'],
                'segment_options' => [
                    [],
                    ['alias' => 'b'],
                    [],
                ],
            ],
        ],
        [
            'expression' => 'users[alias:u,join:left]__posts__content',
            'expected' => [
                'segments_count' => 3,
                'segment_names' => ['users', 'posts', 'content'],
                'segment_options' => [
                    ['alias' => 'u', 'join' => 'left'],
                    [],
                    [],
                ],
            ],
        ],

        // Paths with join conditions
        [
            'expression' => 'invoices__customers[on:customer_id=id]__name',
            'expected' => [
                'segments_count' => 3,
                'segment_names' => ['invoices', 'customers', 'name'],
                'segment_options' => [
                    [],
                    ['on' => ['customer_id' => 'id']],
                    [],
                ],
            ],
        ],
        [
            'expression' => 'orders__items[rel:order_id=id,alias:i]__price',
            'expected' => [
                'segments_count' => 3,
                'segment_names' => ['orders', 'items', 'price'],
                'segment_options' => [
                    [],
                    ['rel' => ['order_id' => 'id'], 'alias' => 'i'],
                    [],
                ],
            ],
        ],

        // Multiple conditions in the same option
        [
            'expression' => 'orders__items[on:order_id=id,on:branch_id=branch_id]__product',
            'expected' => [
                'segments_count' => 3,
                'segment_names' => ['orders', 'items', 'product'],
                'segment_options' => [
                    [],
                    ['on' => ['order_id' => 'id', 'branch_id' => 'branch_id']],
                    [],
                ],
            ],
        ],

        // Complex combinations
        [
            'expression' => 'invoices[alias:i]__customers[on:customer_id=id,alias:c]__address[on:customer_id=id]__city',
            'expected' => [
                'segments_count' => 4,
                'segment_names' => ['invoices', 'customers', 'address', 'city'],
                'segment_options' => [
                    ['alias' => 'i'],
                    ['on' => ['customer_id' => 'id'], 'alias' => 'c'],
                    ['on' => ['customer_id' => 'id']],
                    [],
                ],
            ],
        ],

        // Multiple option types
        [
            'expression' => 'posts[status:draft,join:left,alias:p]__comments[limit:5]__text',
            'expected' => [
                'segments_count' => 3,
                'segment_names' => ['posts', 'comments', 'text'],
                'segment_options' => [
                    ['status' => 'draft', 'join' => 'left', 'alias' => 'p'],
                    ['limit' => '5'],
                    [],
                ],
            ],
        ],

        // Database-like examples
        [
            'expression' => 'invoices__invoice_details[on:id=invoice_id]__products[on:product_id=id]__category',
            'expected' => [
                'segments_count' => 4,
                'segment_names' => ['invoices', 'invoice_details', 'products', 'category'],
                'segment_options' => [
                    [],
                    ['on' => ['id' => 'invoice_id']],
                    ['on' => ['product_id' => 'id']],
                    [],
                ],
            ],
        ],
        [
            'expression' => 'customers[alias:c]__invoices[on:id=customer_id,join:left]__payments[on:invoice_id=id]__method',
            'expected' => [
                'segments_count' => 4,
                'segment_names' => ['customers', 'invoices', 'payments', 'method'],
                'segment_options' => [
                    ['alias' => 'c'],
                    ['on' => ['id' => 'customer_id'], 'join' => 'left'],
                    ['on' => ['invoice_id' => 'id']],
                    [],
                ],
            ],
        ],

        // Function notation (should be preserved).
        [
            'expression' => 'products__AVG(price)',
            'expected' => [
                'segments_count' => 2,
                'segment_names' => ['products', 'AVG(price)'],
                'segment_options' => [[], []],
            ],
        ],
    ],

    // Invalid path expressions (FAIL).
    'FAIL' => [
        // Empty or malformed paths
        ['expression' => ''],                          // Empty path
        ['expression' => '__'],                        // Just separator
        ['expression' => 'author__'],                  // Trailing separator
        ['expression' => '__books'],                   // Leading separator

        // Invalid option syntax
        ['expression' => 'author__books[]'],           // Empty options
        ['expression' => 'author__books[invalid]'],    // Invalid option format
        ['expression' => 'author__books[order:]'],     // Empty option value
        ['expression' => 'books[:created_at]'],        // Empty option key

        // Invalid segment naming
        ['expression' => 'author__[order:asc]__books'], // Empty segment name with options
        ['expression' => '!invalid__name'],             // Invalid characters in name

        // Invalid equals syntax
        ['expression' => 'invoices__customers[on:=id]'],      // Empty left side
        ['expression' => 'invoices__customers[on:customer_id=]'], // Empty right side
    ],
];
