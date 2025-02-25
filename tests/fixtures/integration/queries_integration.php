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
            'sql' => [
                'sql' => 'SELECT * FROM customers WHERE (status = :status)',
                'parameters' => ['status' => 'active'],
            ],
            'query' => [
                'table' => 'customers',
                'where' => 'status?=active',
            ],
        ],
        'expensive_products' => [
            'description' => 'Find expensive products',
            'sql' => [
                'sql' => 'SELECT * FROM products WHERE (price > :price)',
                'parameters' => ['price' => '1000'],
            ],
            'query' => [
                'table' => 'products',
                'where' => 'price?>1000',
            ],
        ],
        'recent_invoices' => [
            'description' => 'Find March 2024 invoices',
            'sql' => [
                'sql' => 'SELECT * FROM invoices WHERE (DATE(date) >= :start AND DATE(date) < :end)',
                'parameters' => [
                    'start' => '2024-03-01',
                    'end' => '2024-03-02',
                ],
            ],
            'query' => [
                'table' => 'invoices',
                'where' => 'date?date:20240301',
            ],
        ],

        // Operadores estándar (=, !=, >, <, >=, <=).
        'equal_match' => [
            'description' => 'Find customer by exact tax_id',
            'sql' => [
                'sql' => 'SELECT * FROM customers WHERE (tax_id = :value)',
                'parameters' => ['value' => '123456789'],
            ],
            'query' => [
                'table' => 'customers',
                'where' => 'tax_id?=123456789',
            ],
        ],
        'not_equal_match' => [
            'description' => 'Find non-electronic products',
            'sql' => [
                'sql' => 'SELECT * FROM products WHERE (category != :value)',
                'parameters' => ['value' => 'electronics'],
            ],
            'query' => [
                'table' => 'products',
                'where' => 'category?!=electronics',
            ],
        ],
        'greater_than' => [
            'description' => 'Find expensive products (>1000)',
            'sql' => [
                'sql' => 'SELECT * FROM products WHERE (price > :value)',
                'parameters' => ['value' => '1000'],
            ],
            'query' => [
                'table' => 'products',
                'where' => 'price?>1000',
            ],
        ],

        // LIKE operators.
        'contains_case_sensitive' => [
            'description' => 'Find products with "License" in name',
            'sql' => [
                'sql' => 'SELECT * FROM products WHERE (name LIKE :value)',
                'parameters' => ['value' => '%License%'],
            ],
            'query' => [
                'table' => 'products',
                'where' => 'name?~~License',
            ],
        ],
        'starts_with' => [
            'description' => 'Find products starting with "Tech"',
            'sql' => [
                'sql' => 'SELECT * FROM products WHERE (name LIKE :value)',
                'parameters' => ['value' => 'Tech%'],
            ],
            'query' => [
                'table' => 'products',
                'where' => 'name?^Tech',
            ],
        ],

        // IN operators.
        'status_in_list' => [
            'description' => 'Find invoices in specific states',
            'sql' => [
                'sql' => 'SELECT * FROM invoices WHERE (status IN (:value1, :value2))',
                'parameters' => ['value1' => 'paid', 'value2' => 'issued'],
            ],
            'query' => [
                'table' => 'invoices',
                'where' => 'status?in:paid,issued',
            ],
        ],

        // Range operators.
        'price_between' => [
            'description' => 'Find products in price range',
            'sql' => [
                'sql' => 'SELECT * FROM products WHERE (price BETWEEN :value1 AND :value2)',
                'parameters' => ['value1' => '100', 'value2' => '1000'],
            ],
            'query' => [
                'table' => 'products',
                'where' => 'price?between:100,1000',
            ],
        ],

        // NULL operators.
        'soft_deleted' => [
            'description' => 'Find soft deleted records',
            'sql' => [
                'sql' => 'SELECT * FROM customers WHERE (deleted_at IS NOT NULL)',
                'parameters' => [],
            ],
            'query' => [
                'table' => 'customers',
                'where' => 'deleted_at?isnot:null',
            ],
        ],

        // Date operators (usando las funciones de SQLite).
        'march_invoices' => [
            'description' => 'Find invoices from March 2024',
            'sql' => [
                'sql' => 'SELECT * FROM invoices WHERE (strftime("%Y%m", date) = :value)',
                'parameters' => ['value' => '202403'],
            ],
            'query' => [
                'table' => 'invoices',
                'where' => 'date?period:202403',
            ],
        ],

        // Bitwise operators.
        'taxable_products' => [
            'description' => 'Find taxable products (flag 1)',
            'sql' => [
                'sql' => 'SELECT * FROM products WHERE (flags & :value)',
                'parameters' => ['value' => '1'],
            ],
            'query' => [
                'table' => 'products',
                'where' => 'flags?b&1',
            ],
        ],

        // Composite AND conditions.
        'active_expensive_products' => [
            'description' => 'Find active products with price > 200',
            'sql' => [
                'sql' => 'SELECT * FROM products WHERE (flags & :value1 AND price > :value2)',
                'parameters' => ['value1' => '1', 'value2' => '200'],
            ],
            'query' => [
                'table' => 'products',
                'where' => ['flags?b&1', 'price?>200'],
            ],
        ],

        // Composite OR conditions.
        'electronics_or_expensive' => [
            'description' => 'Find electronics OR expensive products',
            'sql' => [
                'sql' => 'SELECT * FROM products WHERE (category = :value1 OR price > :value2)',
                'parameters' => ['value1' => 'electronics', 'value2' => '500'],
            ],
            'query' => [
                'table' => 'products',
                'where' => 'category?=electronics',
                'orWhere' => 'price?>500',
            ],
        ],

        // Mixed conditions (AND + OR).
        'complex_status_condition' => [
            'description' => 'Find active customers who are persons OR have tax_id starting with 78',
            'sql' => [
                'sql' => 'SELECT * FROM customers WHERE (status = :value1 AND (type = :value2 OR tax_id LIKE :value3))',
                'parameters' => ['value1' => 'active', 'value2' => 'person', 'value3' => '78%'],
            ],
            'query' => [
                'table' => 'customers',
                'where' => 'status?=active',
                'andWhereOr' => ['type?=person', 'tax_id?^78'],
            ],
        ],

        // Multiple conditions with different operators.
        'invoices_complex_filter' => [
            'description' => 'Find paid invoices with total > 1000 OR invoices from March 2024',
            'sql' => [
                'sql' => 'SELECT * FROM invoices WHERE ((status = :value1 AND total > :value2) OR strftime("%Y%m", date) = :value3)',
                'parameters' => ['value1' => 'paid', 'value2' => '1000', 'value3' => '202403'],
            ],
            'query' => [
                'table' => 'invoices',
                'where' => ['status?=paid', 'total?>1000'],
                'orWhere' => 'date?period:202403',
            ],
        ],

        // Grupos AND dentro de OR.
        'premium_or_high_price_hardware' => [
            'description' => 'Find software products OR expensive hardware (category=hardware AND price > 200)',
            'sql' => [
                'sql' => 'SELECT * FROM products WHERE (category = :value1 OR (category = :value2 AND price > :value3))',
                'parameters' => ['value1' => 'software', 'value2' => 'hardware', 'value3' => '200'],
            ],
            'query' => [
                'table' => 'products',
                'where' => 'category?=software',
                'orWhere' => ['category?=hardware', 'price?>200'],
            ],
        ],

        // NOT IN con múltiples valores.
        'excluded_categories' => [
            'description' => 'Find products not in specific categories',
            'sql' => [
                'sql' => 'SELECT * FROM products WHERE (category NOT IN (:value1, :value2, :value3))',
                'parameters' => ['value1' => 'services', 'value2' => 'food', 'value3' => 'software'],
            ],
            'query' => [
                'table' => 'products',
                'where' => 'category?notin:services,food,software',
            ],
        ],

        // Múltiples ORs encadenados.
        'multi_status_filter' => [
            'description' => 'Find invoices with several possible status combinations',
            'sql' => [
                'sql' => 'SELECT * FROM invoices WHERE (status = :value1 OR status = :value2 OR (status = :value3 AND total > :value4))',
                'parameters' => ['value1' => 'cancelled', 'value2' => 'draft', 'value3' => 'issued', 'value4' => '1000'],
            ],
            'query' => [
                'table' => 'invoices',
                'where' => 'status?=cancelled',
                'orWhere' => ['status?=draft', ['status?=issued', 'total?>1000']],
            ],
        ],

        // Condiciones anidadas más complejas.
        'complex_nested_conditions' => [
            'description' => 'Find orders with complex nested conditions',
            'sql' => [
                'sql' => 'SELECT * FROM invoices WHERE (((status = :value1 AND total > :value2) OR (status = :value3 AND date >= :value4)) AND customer_id IN (:value5, :value6))',
                'parameters' => ['value1' => 'paid', 'value2' => '1000', 'value3' => 'issued', 'value4' => '2024-03-01', 'value5' => '1', 'value6' => '2'],
            ],
            'query' => [
                'table' => 'invoices',
                'where' => 'customer_id?in:1,2',
                'andWhereOr' => [
                    ['status?=paid', 'total?>1000'],
                    ['status?=issued', 'date?>=2024-03-01'],
                ],
            ],
        ],

        // Limite sin offset.
        'limit_only_top_products' => [
            'description' => 'Get top 3 products ordered by price',
            'sql' => [
                'sql' => 'SELECT * FROM products ORDER BY price DESC LIMIT 3',
                'parameters' => [],
            ],
            'query' => [
                'table' => 'products',
                'orderBy' => ['price' => 'DESC'],
                'limit' => 3,
            ],
        ],
        'limit_only_newest_invoices' => [
            'description' => 'Get 2 newest invoices',
            'sql' => [
                'sql' => 'SELECT * FROM invoices ORDER BY created_at DESC LIMIT 2',
                'parameters' => [],
            ],
            'query' => [
                'table' => 'invoices',
                'orderBy' => ['created_at' => 'DESC'],
                'limit' => 2,
            ],
        ],

        // Límite con offset.
        'limit_offset_invoices' => [
            'description' => 'Get invoices with pagination (page 2, size 2)',
            'sql' => [
                'sql' => 'SELECT * FROM invoices ORDER BY id ASC LIMIT 2 OFFSET 2',
                'parameters' => [],
            ],
            'query' => [
                'table' => 'invoices',
                'orderBy' => ['id' => 'ASC'],
                'limit' => 2,
                'offset' => 2,
            ],
        ],
        'limit_offset_customers' => [
            'description' => 'Get customers with pagination (page 2, size 1)',
            'sql' => [
                'sql' => 'SELECT * FROM customers ORDER BY id ASC LIMIT 1 OFFSET 1',
                'parameters' => [],
            ],
            'query' => [
                'table' => 'customers',
                'orderBy' => ['id' => 'ASC'],
                'limit' => 1,
                'offset' => 1,
            ],
        ],

        // Ordenamiento.
        'ordering_products_multiple' => [
            'description' => 'Get products ordered by category ASC and price DESC',
            'sql' => [
                'sql' => 'SELECT * FROM products ORDER BY category ASC, price DESC',
                'parameters' => [],
            ],
            'query' => [
                'table' => 'products',
                'orderBy' => ['category' => 'ASC', 'price' => 'DESC'],
            ],
        ],
        'ordering_invoices_by_status' => [
            'description' => 'Get invoices ordered by status and date',
            'sql' => [
                'sql' => 'SELECT * FROM invoices ORDER BY status ASC, date DESC',
                'parameters' => [],
            ],
            'query' => [
                'table' => 'invoices',
                'orderBy' => ['status' => 'ASC', 'date' => 'DESC'],
            ],
        ],

        // Agrupación.
        'grouping_products_by_category' => [
            'description' => 'Get count of products by category',
            'sql' => [
                'sql' => 'SELECT category, COUNT(*) AS count FROM products GROUP BY category',
                'parameters' => [],
            ],
            'query' => [
                'table' => 'products',
                'select' => 'category, COUNT(*) AS count',
                'groupBy' => 'category',
            ],
        ],
        'grouping_invoices_by_status' => [
            'description' => 'Get sum of invoice totals by status',
            'sql' => [
                'sql' => 'SELECT status, SUM(total) AS total_amount FROM invoices GROUP BY status',
                'parameters' => [],
            ],
            'query' => [
                'table' => 'invoices',
                'select' => 'status, SUM(total) AS total_amount',
                'groupBy' => 'status',
            ],
        ],

        // Condición de agrupación.
        'grouping_with_having_high_value' => [
            'description' => 'Get product categories with average price > 500',
            'sql' => [
                'sql' => 'SELECT category, AVG(price) AS avg_price FROM products GROUP BY category HAVING (AVG(price) > :value)',
                'parameters' => ['value' => '500'],
            ],
            'query' => [
                'table' => 'products',
                'select' => 'category, AVG(price) AS avg_price',
                'groupBy' => 'category',
                'having' => 'AVG(price)?>500',
            ],
        ],
        'grouping_with_having_min_count' => [
            'description' => 'Get statuses with at least 2 invoices',
            'sql' => [
                'sql' => 'SELECT status, COUNT(*) AS count FROM invoices GROUP BY status HAVING (COUNT(*) >= :value)',
                'parameters' => ['value' => '2'],
            ],
            'query' => [
                'table' => 'invoices',
                'select' => 'status, COUNT(*) AS count',
                'groupBy' => 'status',
                'having' => 'COUNT(*)?>1',
            ],
        ],

        // Registros diferentes.
        'distinct_customer_types' => [
            'description' => 'Get distinct customer types',
            'sql' => [
                'sql' => 'SELECT DISTINCT type FROM customers',
                'parameters' => [],
            ],
            'query' => [
                'table' => 'customers',
                'select' => 'type',
                'distinct' => true,
            ],
        ],
        'distinct_invoice_statuses' => [
            'description' => 'Get distinct invoice statuses',
            'sql' => [
                'sql' => 'SELECT DISTINCT status FROM invoices',
                'parameters' => [],
            ],
            'query' => [
                'table' => 'invoices',
                'select' => 'status',
                'distinct' => true,
            ],
        ],

        // Joins.
        'join_invoices_customers' => [
            'description' => 'Get invoices with customer information',
            'sql' => [
                'sql' => 'SELECT i.id, i.number, i.total, c.name AS customer_name FROM invoices AS i INNER JOIN customers AS c ON i.customer_id = c.id',
                'parameters' => [],
            ],
            'query' => [
                'table' => 'invoices',
                'alias' => 'i',
                'select' => 'i.id, i.number, i.total, c.name AS customer_name',
                'innerJoin' => ['table' => 'customers', 'alias' => 'c', 'condition' => 'i.customer_id = c.id'],
            ],
        ],
        'left_join_customers_invoices' => [
            'description' => 'Get all customers with their invoices (if any)',
            'sql' => [
                'sql' => 'SELECT c.name, COUNT(i.id) AS invoice_count FROM customers AS c LEFT JOIN invoices AS i ON c.id = i.customer_id GROUP BY c.id, c.name',
                'parameters' => [],
            ],
            'query' => [
                'table' => 'customers',
                'alias' => 'c',
                'select' => 'c.name, COUNT(i.id) AS invoice_count',
                'leftJoin' => ['table' => 'invoices', 'alias' => 'i', 'condition' => 'c.id = i.customer_id'],
                'groupBy' => ['c.id', 'c.name'],
            ],
        ],

        // Combinación de características.
        'complex_query_filtered_ordered_limited' => [
            'description' => 'Get top 3 paid invoices with total > 1000, ordered by total',
            'sql' => [
                'sql' => 'SELECT * FROM invoices WHERE (status = :value1 AND total > :value2) ORDER BY total DESC LIMIT 3',
                'parameters' => ['value1' => 'paid', 'value2' => '1000'],
            ],
            'query' => [
                'table' => 'invoices',
                'where' => ['status?=paid', 'total?>1000'],
                'orderBy' => ['total' => 'DESC'],
                'limit' => 3,
            ],
        ],
        'complex_query_join_group_having_order' => [
            'description' => 'Get customers with high-value invoices',
            'sql' => [
                'sql' => 'SELECT c.id, c.name, SUM(i.total) AS total_spent FROM customers AS c INNER JOIN invoices AS i ON c.id = i.customer_id WHERE (i.status = :value1) GROUP BY c.id, c.name HAVING (SUM(i.total) > :value2) ORDER BY total_spent DESC',
                'parameters' => ['value1' => 'paid', 'value2' => '1000'],
            ],
            'query' => [
                'table' => 'customers',
                'alias' => 'c',
                'select' => 'c.id, c.name, SUM(i.total) AS total_spent',
                'innerJoin' => ['table' => 'invoices', 'alias' => 'i', 'condition' => 'c.id = i.customer_id'],
                'where' => 'i.status?=paid',
                'groupBy' => ['c.id', 'c.name'],
                'having' => 'SUM(i.total)?>1000',
                'orderBy' => ['total_spent' => 'DESC'],
            ],
        ],

        // Joins usando los paths para la definición de estos.

        'path_join_customers_invoices' => [
            'description' => 'Find invoices with customer information using path syntax',
            'sql' => [
                'sql' => 'SELECT i.id, i.number, i.total, c.name AS customer_name FROM invoices AS i INNER JOIN customers AS c ON i.customer_id = c.id WHERE (c.name IS NOT NULL)',
                'parameters' => [],
            ],
            'query' => [
                'select' => 'i.id, i.number, i.total, c.name AS customer_name',
                'where' => 'invoices[alias:i]__customers[on:customer_id=id,alias:c]__name?isnot:null',
            ],
        ],

        'path_left_join_customers_invoices' => [
            'description' => 'Get customers with their invoices (if any) using path syntax',
            'sql' => [
                'sql' => 'SELECT c.name, COUNT(i.id) AS invoice_count FROM customers AS c LEFT JOIN invoices AS i ON c.id = i.customer_id WHERE ((i.id IS NOT NULL) OR (i.id IS NULL)) GROUP BY c.id, c.name',
                'parameters' => [],
            ],
            'query' => [
                'select' => 'c.name, COUNT(i.id) AS invoice_count',
                'where' => 'customers[alias:c]__invoices[on:id=customer_id,join:left,alias:i]__id?isnot:null',
                'orWhere' => 'customers[alias:c]__invoices[on:id=customer_id,join:left,alias:i]__id?is:null',
                'groupBy' => ['c.id', 'c.name'],
            ],
        ],

        'path_multi_level_join' => [
            'description' => 'Find products in customer invoices using multi-level path',
            'sql' => [
                'sql' => 'SELECT p.name AS product_name, i.number AS invoice_number, c.name AS customer_name FROM products AS p INNER JOIN invoice_details AS id ON p.id = id.product_id INNER JOIN invoices AS i ON id.invoice_id = i.id INNER JOIN customers AS c ON i.customer_id = c.id WHERE (c.name IS NOT NULL)',
                'parameters' => [],
            ],
            'query' => [
                'select' => 'p.name AS product_name, i.number AS invoice_number, c.name AS customer_name',
                'where' => 'products[alias:p]__invoice_details[on:id=product_id,alias:id]__invoices[on:invoice_id=id,alias:i]__customers[on:customer_id=id,alias:c]__name?isnot:null',
            ],
        ],

        'path_join_with_conditions' => [
            'description' => 'Find active customers with paid invoices',
            'sql' => [
                'sql' => 'SELECT c.name, i.number, i.total FROM customers AS c INNER JOIN invoices AS i ON c.id = i.customer_id WHERE (c.status = :status AND i.status = :invoice_status)',
                'parameters' => ['status' => 'active', 'invoice_status' => 'paid'],
            ],
            'query' => [
                'select' => 'c.name, i.number, i.total',
                'where' => [
                    'customers[alias:c]__status?=active',
                    'customers[alias:c]__invoices[on:id=customer_id,alias:i]__status?=paid',
                ],
            ],
        ],

        'path_join_with_subquery_condition' => [
            'description' => 'Find customers with invoices over 1000',
            'sql' => [
                'sql' => 'SELECT c.name, i.number, i.total FROM customers AS c INNER JOIN invoices AS i ON c.id = i.customer_id WHERE (i.total > :total)',
                'parameters' => ['total' => '1000'],
            ],
            'query' => [
                'select' => 'c.name, i.number, i.total',
                'where' => 'customers[alias:c]__invoices[on:id=customer_id,alias:i]__total?>1000',
            ],
        ],

        'path_join_payments' => [
            'description' => 'Find customers with pending payments',
            'sql' => [
                'sql' => 'SELECT c.name, i.number, p.amount, p.method FROM customers AS c INNER JOIN invoices AS i ON c.id = i.customer_id INNER JOIN payments AS p ON i.id = p.invoice_id WHERE (p.status = :status)',
                'parameters' => ['status' => 'pending'],
            ],
            'query' => [
                'select' => 'c.name, i.number, p.amount, p.method',
                'where' => 'customers[alias:c]__invoices[on:id=customer_id,alias:i]__payments[on:id=invoice_id,alias:p]__status?=pending',
            ],
        ],

        'path_mixed_join_types' => [
            'description' => 'Find all products with their invoice details (if any)',
            'sql' => [
                'sql' => 'SELECT p.name, SUM(id.quantity) AS total_sold FROM products AS p LEFT JOIN invoice_details AS id ON p.id = id.product_id WHERE ((id.quantity IS NULL) OR (id.quantity IS NOT NULL)) GROUP BY p.id, p.name',
                'parameters' => [],
            ],
            'query' => [
                'select' => 'p.name, SUM(id.quantity) AS total_sold',
                'where' => 'products[alias:p]__invoice_details[on:id=product_id,join:left,alias:id]__quantity?is:null',
                'orWhere' => 'products[alias:p]__invoice_details[on:id=product_id,join:left,alias:id]__quantity?isnot:null',
                'groupBy' => ['p.id', 'p.name'],
            ],
        ],

        'path_complex_join_and_filter' => [
            'description' => 'Find electronic products with paid invoices for company customers',
            'sql' => [
                'sql' => 'SELECT p.name, i.number, c.name AS customer FROM products AS p INNER JOIN invoice_details AS id ON p.id = id.product_id INNER JOIN invoices AS i ON id.invoice_id = i.id INNER JOIN customers AS c ON i.customer_id = c.id WHERE (p.category = :category AND i.status = :status AND c.type = :type)',
                'parameters' => ['category' => 'electronics', 'status' => 'paid', 'type' => 'company'],
            ],
            'query' => [
                'select' => 'p.name, i.number, c.name AS customer',
                'where' => [
                    'products[alias:p]__category?=electronics',
                    'products[alias:p]__invoice_details[on:id=product_id,alias:id]__invoices[on:invoice_id=id,alias:i]__status?=paid',
                    'products[alias:p]__invoice_details[on:id=product_id,alias:id]__invoices[on:invoice_id=id,alias:i]__customers[on:customer_id=id,alias:c]__type?=company',
                ],
            ],
        ],

        'path_right_join_test' => [
            'description' => 'Test right join with path syntax',
            'sql' => [
                'sql' => 'SELECT c.name, i.number FROM customers AS c RIGHT JOIN invoices AS i ON c.id = i.customer_id',
                'parameters' => [],
            ],
            'query' => [
                'select' => 'c.name, i.number',
                'where' => 'customers[alias:c]__invoices[on:id=customer_id,join:right,alias:i]__number?isnot:null',
            ],
        ],

        'path_join_multiple_conditions' => [
            'description' => 'Join with multiple conditions (simulated)',
            'sql' => [
                'sql' => 'SELECT i.number, p.date, p.amount FROM invoices AS i INNER JOIN payments AS p ON i.id = p.invoice_id WHERE (i.status = :status)',
                'parameters' => ['status' => 'paid'],
            ],
            'query' => [
                'select' => 'i.number, p.date, p.amount',
                'where' => [
                    'invoices[alias:i]__status?=paid',
                    'invoices[alias:i]__payments[on:id=invoice_id,alias:p]__amount?isnot:null',
                ],
            ],
        ],

        'path_join_with_order_and_limit' => [
            'description' => 'Join with ordering and limit',
            'sql' => [
                'sql' => 'SELECT c.name, SUM(i.total) AS total_spent FROM customers AS c INNER JOIN invoices AS i ON c.id = i.customer_id WHERE (i.total IS NOT NULL) GROUP BY c.id, c.name ORDER BY total_spent DESC LIMIT 3',
                'parameters' => [],
            ],
            'query' => [
                'select' => 'c.name, SUM(i.total) AS total_spent',
                'where' => 'customers[alias:c]__invoices[on:id=customer_id,alias:i]__total?isnot:null',
                'groupBy' => ['c.id', 'c.name'],
                'orderBy' => ['SUM(i.total)' => 'DESC'],
                'limit' => 3,
            ],
        ],

        'path_join_with_having' => [
            'description' => 'Join with having clause',
            'sql' => [
                'sql' => 'SELECT c.type, AVG(i.total) AS avg_spent FROM customers AS c INNER JOIN invoices AS i ON c.id = i.customer_id WHERE (i.total IS NOT NULL) GROUP BY c.type HAVING AVG(i.total) > :avg',
                'parameters' => ['avg' => '1000'],
            ],
            'query' => [
                'select' => 'c.type, AVG(i.total) AS avg_spent',
                'where' => 'customers[alias:c]__invoices[on:id=customer_id,alias:i]__total?isnot:null',
                'groupBy' => ['c.type'],
                'having' => 'AVG(i.total)?>1000',
            ],
        ],

        'path_join_or_conditions' => [
            'description' => 'Join with OR conditions',
            'sql' => [
                'sql' => 'SELECT p.name, i.number FROM products AS p INNER JOIN invoice_details AS id ON p.id = id.product_id INNER JOIN invoices AS i ON id.invoice_id = i.id WHERE (((p.category = :category1) OR (p.category = :category2)) AND i.number IS NOT NULL)',
                'parameters' => ['category1' => 'electronics', 'category2' => 'software'],
            ],
            'query' => [
                'select' => 'p.name, i.number',
                'where' => 'products[alias:p]__category?=electronics',
                'orWhere' => 'products[alias:p]__category?=software',
                'andWhere' => 'products[alias:p]__invoice_details[on:id=product_id,alias:id]__invoices[on:invoice_id=id,alias:i]__number?isnot:null',
            ],
        ],

        'path_self_join_simulation' => [
            'description' => 'Simulated self-join using paths',
            'sql' => [
                'sql' => 'SELECT p1.name AS product1, p2.name AS product2, id1.invoice_id FROM products AS p1 INNER JOIN invoice_details AS id1 ON p1.id = id1.product_id INNER JOIN invoice_details AS id2 ON id1.invoice_id = id2.invoice_id INNER JOIN products AS p2 ON id2.product_id = p2.id WHERE (p1.id != p2.id)',
                'parameters' => [],
            ],
            'query' => [
                'select' => 'p1.name AS product1, p2.name AS product2, id1.invoice_id',
                'where' => [
                    'products[alias:p1]__invoice_details[on:id=product_id,alias:id1]__invoice_id?isnot:null',
                    'products[alias:p1]__invoice_details[on:id=product_id,alias:id1]__invoices[on:invoice_id=id,alias:i]__invoice_details[on:id=invoice_id,alias:id2]__products[on:product_id=id,alias:p2]__id?E!=p1.id',
                ],
            ],
        ],
    ],
];
