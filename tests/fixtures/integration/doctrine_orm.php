<?php

declare(strict_types=1);

/**
 * Derafu: Query - Expressive Path-Based Query Builder for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

use Derafu\Query\Bridge\Exception\UnsupportedOperatorException;
use Derafu\TestsQuery\Integration\Bridge\DoctrineORM\Entity\Customer;
use Derafu\TestsQuery\Integration\Bridge\DoctrineORM\Entity\Invoice;
use Derafu\TestsQuery\Integration\Bridge\DoctrineORM\Entity\Product;

return [
    'cases' => [

        // ----------------------------------------------------------------
        // Standard comparison operators — same expression style as queries_integration.php.
        // Single-segment paths are auto-qualified with the root entity alias.
        // ----------------------------------------------------------------

        'find_active_customers' => [
            'description' => 'Find all active customers',
            'sql' => [
                'sql' => 'SELECT c.id AS id, c.name AS name, c.status AS status FROM customers c WHERE (c.status = :status)',
                'parameters' => ['status' => 'active'],
            ],
            'query' => [
                'table' => Customer::class,
                'alias' => 'c',
                'select' => 'c.id AS id, c.name AS name, c.status AS status',
                'where' => 'status?=active',
            ],
        ],

        'expensive_products' => [
            'description' => 'Find expensive products (price > 1000)',
            'sql' => [
                'sql' => 'SELECT p.id AS id, p.name AS name, p.category AS category FROM products p WHERE (p.price > :price)',
                'parameters' => ['price' => '1000'],
            ],
            'query' => [
                'table' => Product::class,
                'alias' => 'p',
                'select' => 'p.id AS id, p.name AS name, p.category AS category',
                'where' => 'price?>1000',
            ],
        ],

        'equal_match' => [
            'description' => 'Find customer by exact tax_id',
            'sql' => [
                'sql' => 'SELECT c.id AS id, c.name AS name FROM customers c WHERE (c.tax_id = :value)',
                'parameters' => ['value' => '123456789'],
            ],
            'query' => [
                'table' => Customer::class,
                'alias' => 'c',
                'select' => 'c.id AS id, c.name AS name',
                'where' => 'tax_id?=123456789',
            ],
        ],

        'not_equal_match' => [
            'description' => 'Find non-electronic products',
            'sql' => [
                'sql' => 'SELECT p.id AS id, p.name AS name, p.category AS category FROM products p WHERE (p.category != :value)',
                'parameters' => ['value' => 'electronics'],
            ],
            'query' => [
                'table' => Product::class,
                'alias' => 'p',
                'select' => 'p.id AS id, p.name AS name, p.category AS category',
                'where' => 'category?!=electronics',
            ],
        ],

        'greater_than' => [
            'description' => 'Find expensive products (>1000)',
            'sql' => [
                'sql' => 'SELECT p.id AS id, p.name AS name FROM products p WHERE (p.price > :value)',
                'parameters' => ['value' => '1000'],
            ],
            'query' => [
                'table' => Product::class,
                'alias' => 'p',
                'select' => 'p.id AS id, p.name AS name',
                'where' => 'price?>1000',
            ],
        ],

        'less_than_or_equal' => [
            'description' => 'Find products with price <= 200',
            'sql' => [
                'sql' => 'SELECT p.id AS id, p.name AS name FROM products p WHERE (p.price <= :value)',
                'parameters' => ['value' => '200'],
            ],
            'query' => [
                'table' => Product::class,
                'alias' => 'p',
                'select' => 'p.id AS id, p.name AS name',
                'where' => 'price?<=200',
            ],
        ],

        // ----------------------------------------------------------------
        // LIKE operators.
        // ----------------------------------------------------------------

        'contains_case_sensitive' => [
            'description' => 'Find products with "License" in name',
            'sql' => [
                'sql' => 'SELECT p.id AS id, p.name AS name FROM products p WHERE (p.name LIKE :value)',
                'parameters' => ['value' => '%License%'],
            ],
            'query' => [
                'table' => Product::class,
                'alias' => 'p',
                'select' => 'p.id AS id, p.name AS name',
                'where' => 'name?~~License',
            ],
        ],

        'starts_with' => [
            'description' => 'Find products starting with "Tech"',
            'sql' => [
                'sql' => 'SELECT p.id AS id, p.name AS name FROM products p WHERE (p.name LIKE :value)',
                'parameters' => ['value' => 'Tech%'],
            ],
            'query' => [
                'table' => Product::class,
                'alias' => 'p',
                'select' => 'p.id AS id, p.name AS name',
                'where' => 'name?^Tech',
            ],
        ],

        // ----------------------------------------------------------------
        // IN / NOT IN operators.
        // ----------------------------------------------------------------

        'status_in_list' => [
            'description' => 'Find invoices in specific states',
            'sql' => [
                'sql' => 'SELECT i.id AS id, i.number AS number, i.status AS status FROM invoices i WHERE (i.status IN (:value1, :value2))',
                'parameters' => ['value1' => 'paid', 'value2' => 'issued'],
            ],
            'query' => [
                'table' => Invoice::class,
                'alias' => 'i',
                'select' => 'i.id AS id, i.number AS number, i.status AS status',
                'where' => 'status?in:paid,issued',
            ],
        ],

        'excluded_categories' => [
            'description' => 'Find products not in specific categories',
            'sql' => [
                'sql' => 'SELECT p.id AS id, p.name AS name, p.category AS category FROM products p WHERE (p.category NOT IN (:value1, :value2, :value3))',
                'parameters' => ['value1' => 'services', 'value2' => 'food', 'value3' => 'software'],
            ],
            'query' => [
                'table' => Product::class,
                'alias' => 'p',
                'select' => 'p.id AS id, p.name AS name, p.category AS category',
                'where' => 'category?notin:services,food,software',
            ],
        ],

        // ----------------------------------------------------------------
        // Range operator.
        // ----------------------------------------------------------------

        'price_between' => [
            'description' => 'Find products in price range',
            'sql' => [
                'sql' => 'SELECT p.id AS id, p.name AS name FROM products p WHERE (p.price BETWEEN :value1 AND :value2)',
                'parameters' => ['value1' => '100', 'value2' => '1000'],
            ],
            'query' => [
                'table' => Product::class,
                'alias' => 'p',
                'select' => 'p.id AS id, p.name AS name',
                'where' => 'price?between:100,1000',
            ],
        ],

        // ----------------------------------------------------------------
        // NULL operators.
        // ----------------------------------------------------------------

        'soft_deleted' => [
            'description' => 'Find soft deleted records',
            'sql' => [
                'sql' => 'SELECT c.id AS id, c.name AS name FROM customers c WHERE (c.deleted_at IS NOT NULL)',
                'parameters' => [],
            ],
            'query' => [
                'table' => Customer::class,
                'alias' => 'c',
                'select' => 'c.id AS id, c.name AS name',
                'where' => 'deleted_at?isnot:null',
            ],
        ],

        'not_soft_deleted' => [
            'description' => 'Find non-deleted records',
            'sql' => [
                'sql' => 'SELECT c.id AS id, c.name AS name FROM customers c WHERE (c.deleted_at IS NULL)',
                'parameters' => [],
            ],
            'query' => [
                'table' => Customer::class,
                'alias' => 'c',
                'select' => 'c.id AS id, c.name AS name',
                'where' => 'deleted_at?is:null',
            ],
        ],

        // ----------------------------------------------------------------
        // Composite AND conditions.
        // ----------------------------------------------------------------

        'active_high_price_products' => [
            'description' => 'Find software products with price > 200',
            'sql' => [
                'sql' => 'SELECT p.id AS id, p.name AS name, p.category AS category FROM products p WHERE (p.category = :value1 AND p.price > :value2)',
                'parameters' => ['value1' => 'software', 'value2' => '200'],
            ],
            'query' => [
                'table' => Product::class,
                'alias' => 'p',
                'select' => 'p.id AS id, p.name AS name, p.category AS category',
                'where' => ['category?=software', 'price?>200'],
            ],
        ],

        // ----------------------------------------------------------------
        // Composite OR conditions.
        // ----------------------------------------------------------------

        'electronics_or_hardware' => [
            'description' => 'Find electronics OR hardware products',
            'sql' => [
                'sql' => 'SELECT p.id AS id, p.name AS name, p.category AS category FROM products p WHERE (p.category = :value1 OR p.category = :value2)',
                'parameters' => ['value1' => 'electronics', 'value2' => 'hardware'],
            ],
            'query' => [
                'table' => Product::class,
                'alias' => 'p',
                'select' => 'p.id AS id, p.name AS name, p.category AS category',
                'where' => 'category?=electronics',
                'orWhere' => 'category?=hardware',
            ],
        ],

        // ----------------------------------------------------------------
        // Mixed AND + OR conditions.
        // ----------------------------------------------------------------

        'complex_status_condition' => [
            'description' => 'Find active customers who are persons OR have tax_id starting with 78',
            'sql' => [
                'sql' => 'SELECT c.id AS id, c.name AS name, c.type AS type FROM customers c WHERE (c.status = :value1 AND (c.type = :value2 OR c.tax_id LIKE :value3))',
                'parameters' => ['value1' => 'active', 'value2' => 'person', 'value3' => '78%'],
            ],
            'query' => [
                'table' => Customer::class,
                'alias' => 'c',
                'select' => 'c.id AS id, c.name AS name, c.type AS type',
                'where' => 'status?=active',
                'andWhereOr' => ['type?=person', 'tax_id?^78'],
            ],
        ],

        'multi_status_filter' => [
            'description' => 'Find invoices with several possible status combinations',
            'sql' => [
                'sql' => 'SELECT i.id AS id, i.number AS number, i.status AS status FROM invoices i WHERE (i.status = :value1 OR i.status = :value2 OR (i.status = :value3 AND i.total > :value4))',
                'parameters' => ['value1' => 'cancelled', 'value2' => 'draft', 'value3' => 'issued', 'value4' => '1000'],
            ],
            'query' => [
                'table' => Invoice::class,
                'alias' => 'i',
                'select' => 'i.id AS id, i.number AS number, i.status AS status',
                'where' => 'status?=cancelled',
                'orWhere' => ['status?=draft', ['status?=issued', 'total?>1000']],
            ],
        ],

        // ----------------------------------------------------------------
        // Pagination.
        // ----------------------------------------------------------------

        'limit_only_top_products' => [
            'description' => 'Get top 3 products ordered by price',
            'sql' => [
                'sql' => 'SELECT p.id AS id, p.name AS name FROM products p ORDER BY p.price DESC LIMIT 3',
                'parameters' => [],
            ],
            'query' => [
                'table' => Product::class,
                'alias' => 'p',
                'select' => 'p.id AS id, p.name AS name',
                'orderBy' => ['p.price' => 'DESC'],
                'limit' => 3,
            ],
            'preserveOrder' => true,
        ],

        'limit_offset_invoices' => [
            'description' => 'Get invoices with pagination (page 2, size 2)',
            'sql' => [
                'sql' => 'SELECT i.id AS id, i.number AS number FROM invoices i ORDER BY i.id ASC LIMIT 2 OFFSET 2',
                'parameters' => [],
            ],
            'query' => [
                'table' => Invoice::class,
                'alias' => 'i',
                'select' => 'i.id AS id, i.number AS number',
                'orderBy' => ['i.id' => 'ASC'],
                'limit' => 2,
                'offset' => 2,
            ],
            'preserveOrder' => true,
        ],

        'limit_offset_customers' => [
            'description' => 'Get customers with pagination (page 2, size 1)',
            'sql' => [
                'sql' => 'SELECT c.id AS id, c.name AS name FROM customers c ORDER BY c.id ASC LIMIT 1 OFFSET 1',
                'parameters' => [],
            ],
            'query' => [
                'table' => Customer::class,
                'alias' => 'c',
                'select' => 'c.id AS id, c.name AS name',
                'orderBy' => ['c.id' => 'ASC'],
                'limit' => 1,
                'offset' => 1,
            ],
            'preserveOrder' => true,
        ],

        // ----------------------------------------------------------------
        // Ordering.
        // ----------------------------------------------------------------

        'ordering_products_multiple' => [
            'description' => 'Get products ordered by category ASC and price DESC',
            'sql' => [
                'sql' => 'SELECT p.id AS id, p.name AS name, p.category AS category FROM products p ORDER BY p.category ASC, p.price DESC',
                'parameters' => [],
            ],
            'query' => [
                'table' => Product::class,
                'alias' => 'p',
                'select' => 'p.id AS id, p.name AS name, p.category AS category',
                'orderBy' => ['p.category' => 'ASC', 'p.price' => 'DESC'],
            ],
            'preserveOrder' => true,
        ],

        // ----------------------------------------------------------------
        // DISTINCT.
        // ----------------------------------------------------------------

        'distinct_customer_types' => [
            'description' => 'Get distinct customer types',
            'sql' => [
                'sql' => 'SELECT DISTINCT c.type AS type FROM customers c',
                'parameters' => [],
            ],
            'query' => [
                'table' => Customer::class,
                'alias' => 'c',
                'select' => 'c.type AS type',
                'distinct' => true,
            ],
        ],

        'distinct_invoice_statuses' => [
            'description' => 'Get distinct invoice statuses',
            'sql' => [
                'sql' => 'SELECT DISTINCT i.status AS status FROM invoices i',
                'parameters' => [],
            ],
            'query' => [
                'table' => Invoice::class,
                'alias' => 'i',
                'select' => 'i.status AS status',
                'distinct' => true,
            ],
        ],

        // ----------------------------------------------------------------
        // GROUP BY.
        // ----------------------------------------------------------------

        'grouping_products_by_category' => [
            'description' => 'Get count of products by category',
            'sql' => [
                'sql' => 'SELECT p.category AS category, COUNT(p.id) AS count FROM products p GROUP BY p.category',
                'parameters' => [],
            ],
            'query' => [
                'table' => Product::class,
                'alias' => 'p',
                'select' => 'p.category AS category, COUNT(p.id) AS count',
                'groupBy' => 'p.category',
            ],
        ],

        'grouping_invoices_by_status' => [
            'description' => 'Get sum of invoice totals by status',
            'sql' => [
                'sql' => 'SELECT i.status AS status, SUM(i.total) AS total_amount FROM invoices i GROUP BY i.status',
                'parameters' => [],
            ],
            'query' => [
                'table' => Invoice::class,
                'alias' => 'i',
                'select' => 'i.status AS status, SUM(i.total) AS total_amount',
                'groupBy' => 'i.status',
            ],
        ],

        // ----------------------------------------------------------------
        // GROUP BY + HAVING.
        // Single-segment HAVING paths are also auto-qualified with the root alias.
        // ----------------------------------------------------------------

        'grouping_with_having_min_count' => [
            'description' => 'Get invoice statuses with at least 2 invoices',
            'sql' => [
                'sql' => 'SELECT i.status AS status, COUNT(i.id) AS count FROM invoices i GROUP BY i.status HAVING (COUNT(i.id) >= :value)',
                'parameters' => ['value' => '2'],
            ],
            'query' => [
                'table' => Invoice::class,
                'alias' => 'i',
                'select' => 'i.status AS status, COUNT(i.id) AS count',
                'groupBy' => 'i.status',
                'having' => 'COUNT(id)?>1',
            ],
        ],

        // ----------------------------------------------------------------
        // Explicit JOINs (using association paths, not table names).
        // ----------------------------------------------------------------

        'join_invoices_customers' => [
            'description' => 'Get invoices with customer name',
            'sql' => [
                'sql' => 'SELECT i.id AS id, i.number AS number, c.name AS customer_name FROM invoices i INNER JOIN customers c ON i.customer_id = c.id',
                'parameters' => [],
            ],
            'query' => [
                'table' => Invoice::class,
                'alias' => 'i',
                'select' => 'i.id AS id, i.number AS number, c.name AS customer_name',
                'innerJoin' => ['join' => 'i.customer', 'alias' => 'c'],
            ],
        ],

        'left_join_invoices_customers' => [
            'description' => 'Get all invoices with optional customer name',
            'sql' => [
                'sql' => 'SELECT i.id AS id, i.number AS number, c.name AS customer_name FROM invoices i LEFT JOIN customers c ON i.customer_id = c.id',
                'parameters' => [],
            ],
            'query' => [
                'table' => Invoice::class,
                'alias' => 'i',
                'select' => 'i.id AS id, i.number AS number, c.name AS customer_name',
                'leftJoin' => ['join' => 'i.customer', 'alias' => 'c'],
            ],
        ],

        'join_with_where_condition' => [
            'description' => 'Get paid invoices with customer name',
            'sql' => [
                'sql' => 'SELECT i.id AS id, i.number AS number, c.name AS customer_name FROM invoices i INNER JOIN customers c ON i.customer_id = c.id WHERE (i.status = :status)',
                'parameters' => ['status' => 'paid'],
            ],
            'query' => [
                'table' => Invoice::class,
                'alias' => 'i',
                'select' => 'i.id AS id, i.number AS number, c.name AS customer_name',
                'innerJoin' => ['join' => 'i.customer', 'alias' => 'c'],
                'where' => 'status?=paid',
            ],
        ],

        // ----------------------------------------------------------------
        // Path-based JOINs — association property names in path segments.
        // Multi-segment paths are not modified (already have alias prefix).
        // ----------------------------------------------------------------

        'path_join_customers_invoices' => [
            'description' => 'Find invoices with customer information using path syntax',
            'sql' => [
                'sql' => 'SELECT i.id AS id, i.number AS number, c.name AS customer_name FROM invoices i INNER JOIN customers c ON i.customer_id = c.id WHERE (c.name IS NOT NULL)',
                'parameters' => [],
            ],
            'query' => [
                'table' => Invoice::class,
                'alias' => 'i',
                'select' => 'i.id AS id, i.number AS number, c.name AS customer_name',
                'where' => 'invoices[alias:i]__customer[alias:c]__name?isnot:null',
            ],
        ],

        'path_join_from_customers_to_invoices' => [
            'description' => 'Find paid invoices starting from customer using path syntax',
            'sql' => [
                'sql' => 'SELECT c.name AS customer_name, i.number AS number, i.status AS status FROM customers c INNER JOIN invoices i ON c.id = i.customer_id WHERE (i.status = :status)',
                'parameters' => ['status' => 'paid'],
            ],
            'query' => [
                'table' => Customer::class,
                'alias' => 'c',
                'select' => 'c.name AS customer_name, i.number AS number, i.status AS status',
                'where' => 'customers[alias:c]__invoices[alias:i]__status?=paid',
            ],
        ],

        'path_join_payments' => [
            'description' => 'Find pending payments via customer-invoice path',
            'sql' => [
                'sql' => 'SELECT c.name AS customer_name, i.number AS invoice_number, p.method AS method, p.status AS payment_status FROM customers c INNER JOIN invoices i ON c.id = i.customer_id INNER JOIN payments p ON i.id = p.invoice_id WHERE (p.status = :status)',
                'parameters' => ['status' => 'pending'],
            ],
            'query' => [
                'table' => Customer::class,
                'alias' => 'c',
                'select' => 'c.name AS customer_name, i.number AS invoice_number, p.method AS method, p.status AS payment_status',
                'where' => 'customers[alias:c]__invoices[alias:i]__payments[alias:p]__status?=pending',
            ],
        ],

        'path_left_join' => [
            'description' => 'Get customers with their invoice count (if any)',
            'sql' => [
                'sql' => 'SELECT c.name AS name, COUNT(i.id) AS invoice_count FROM customers c LEFT JOIN invoices i ON c.id = i.customer_id GROUP BY c.id, c.name',
                'parameters' => [],
            ],
            'query' => [
                'table' => Customer::class,
                'alias' => 'c',
                'select' => 'c.name AS name, COUNT(i.id) AS invoice_count',
                'where' => 'customers[alias:c]__invoices[join:left,alias:i]__status?isnot:null',
                'orWhere' => 'customers[alias:c]__invoices[join:left,alias:i]__status?is:null',
                'groupBy' => ['c.id', 'c.name'],
            ],
        ],

        'path_join_with_conditions' => [
            'description' => 'Find active customers with paid invoices via paths',
            'sql' => [
                'sql' => 'SELECT c.name AS name, i.number AS number FROM customers c INNER JOIN invoices i ON c.id = i.customer_id WHERE (c.status = :cstatus AND i.status = :istatus)',
                'parameters' => ['cstatus' => 'active', 'istatus' => 'paid'],
            ],
            'query' => [
                'table' => Customer::class,
                'alias' => 'c',
                'select' => 'c.name AS name, i.number AS number',
                'where' => [
                    'customers[alias:c]__status?=active',
                    'customers[alias:c]__invoices[alias:i]__status?=paid',
                ],
            ],
        ],

        // ----------------------------------------------------------------
        // EXISTS subquery cases (___) — rendered as DQL SIZE() or EXISTS().
        // ----------------------------------------------------------------

        'invoices_with_no_payments' => [
            'description' => 'Invoices that have no payments',
            'sql' => [
                'sql' => 'SELECT i.id AS id, i.number AS number, i.status AS status FROM invoices i WHERE NOT EXISTS (SELECT 1 FROM payments p WHERE p.invoice_id = i.id)',
                'parameters' => [],
            ],
            'query' => [
                'table' => Invoice::class,
                'alias' => 'i',
                'select' => 'i.id AS id, i.number AS number, i.status AS status',
                'where' => '___payments[on:id=invoice_id]?is:empty',
            ],
        ],

        'invoices_with_payments' => [
            'description' => 'Invoices that have at least one payment',
            'sql' => [
                'sql' => 'SELECT i.id AS id, i.number AS number, i.status AS status FROM invoices i WHERE EXISTS (SELECT 1 FROM payments p WHERE p.invoice_id = i.id)',
                'parameters' => [],
            ],
            'query' => [
                'table' => Invoice::class,
                'alias' => 'i',
                'select' => 'i.id AS id, i.number AS number, i.status AS status',
                'where' => '___payments[on:id=invoice_id]?isnot:empty',
            ],
        ],

        'customers_with_no_invoices' => [
            'description' => 'Customers that have no invoices',
            'sql' => [
                'sql' => 'SELECT c.id AS id, c.name AS name FROM customers c WHERE NOT EXISTS (SELECT 1 FROM invoices inv WHERE inv.customer_id = c.id)',
                'parameters' => [],
            ],
            'query' => [
                'table' => Customer::class,
                'alias' => 'c',
                'select' => 'c.id AS id, c.name AS name',
                'where' => '___invoices[on:id=customer_id]?is:empty',
            ],
        ],

        'invoices_with_pending_payment' => [
            'description' => 'Invoices that have at least one pending payment',
            'sql' => [
                'sql' => 'SELECT i.id AS id, i.number AS number, i.status AS status FROM invoices i WHERE EXISTS (SELECT 1 FROM payments p WHERE p.invoice_id = i.id AND p.status = :status)',
                'parameters' => ['status' => 'pending'],
            ],
            'query' => [
                'table' => Invoice::class,
                'alias' => 'i',
                'select' => 'i.id AS id, i.number AS number, i.status AS status',
                'where' => '___payments[on:id=invoice_id]__status?=pending',
            ],
        ],

        // Aggregate scalar subquery cases (___assoc__AGG(col)?op:value).

        'invoices_sum_payments_gte' => [
            'description' => 'Invoices where sum of payments >= 1200',
            'sql' => [
                'sql' => 'SELECT i.id AS id, i.number AS number, i.status AS status FROM invoices i WHERE (SELECT SUM(p.amount) FROM payments p WHERE p.invoice_id = i.id) >= :value',
                'parameters' => ['value' => '1200'],
            ],
            'query' => [
                'table' => Invoice::class,
                'alias' => 'i',
                'select' => 'i.id AS id, i.number AS number, i.status AS status',
                'where' => '___payments[on:id=invoice_id]__SUM(amount)?>=1200',
            ],
        ],

        'invoices_count_payments_gt' => [
            'description' => 'Invoices with more than one payment',
            'sql' => [
                'sql' => 'SELECT i.id AS id, i.number AS number, i.status AS status FROM invoices i WHERE (SELECT COUNT(*) FROM payments p WHERE p.invoice_id = i.id) > :value',
                'parameters' => ['value' => '1'],
            ],
            'query' => [
                'table' => Invoice::class,
                'alias' => 'i',
                'select' => 'i.id AS id, i.number AS number, i.status AS status',
                'where' => '___payments[on:id=invoice_id]__COUNT(*)?>1',
            ],
        ],

        'customers_avg_invoice_total_lt' => [
            'description' => 'Customers whose average invoice total is less than 1000',
            'sql' => [
                'sql' => 'SELECT c.id AS id, c.name AS name FROM customers c WHERE (SELECT AVG(i.total) FROM invoices i WHERE i.customer_id = c.id) < :value',
                'parameters' => ['value' => '1000'],
            ],
            'query' => [
                'table' => Customer::class,
                'alias' => 'c',
                'select' => 'c.id AS id, c.name AS name',
                'where' => '___invoices[on:id=customer_id]__AVG(total)?<1000',
            ],
        ],

        // COUNT(*) = 0 / > 0 rewritten to DQL SIZE() = 0 / > 0.

        'invoices_no_payments_count_zero' => [
            'description' => 'Invoices with no payments via COUNT(*) = 0 (rewritten to SIZE() = 0)',
            'sql' => [
                'sql' => 'SELECT i.id AS id, i.number AS number, i.status AS status FROM invoices i WHERE NOT EXISTS (SELECT 1 FROM payments p WHERE p.invoice_id = i.id)',
                'parameters' => [],
            ],
            'query' => [
                'table' => Invoice::class,
                'alias' => 'i',
                'select' => 'i.id AS id, i.number AS number, i.status AS status',
                'where' => '___payments[on:id=invoice_id]__COUNT(*)?=0',
            ],
        ],

        'invoices_has_payments_count_gt_zero' => [
            'description' => 'Invoices with at least one payment via COUNT(*) > 0 (rewritten to SIZE() > 0)',
            'sql' => [
                'sql' => 'SELECT i.id AS id, i.number AS number, i.status AS status FROM invoices i WHERE EXISTS (SELECT 1 FROM payments p WHERE p.invoice_id = i.id)',
                'parameters' => [],
            ],
            'query' => [
                'table' => Invoice::class,
                'alias' => 'i',
                'select' => 'i.id AS id, i.number AS number, i.status AS status',
                'where' => '___payments[on:id=invoice_id]__COUNT(*)?>0',
            ],
        ],

    ],

    // ----------------------------------------------------------------
    // Exception cases: operators incompatible with DQL.
    // ----------------------------------------------------------------

    'exception_cases' => [

        'unsupported_date_operator' => [
            'description' => 'date: operator uses DATE() SQL function, not valid DQL',
            'expression' => 'date?date:20240101',
            'exception' => UnsupportedOperatorException::class,
        ],

        'unsupported_period_operator' => [
            'description' => 'period: operator uses strftime/TO_CHAR, not valid DQL',
            'expression' => 'date?period:202403',
            'exception' => UnsupportedOperatorException::class,
        ],

        'unsupported_bitwise_operator' => [
            'description' => 'b& operator uses SQL bitwise AND, not valid DQL',
            'expression' => 'flags?b&1',
            'exception' => UnsupportedOperatorException::class,
        ],

        'unsupported_regexp_operator' => [
            'description' => '~ operator uses SQL REGEXP, not valid DQL',
            'expression' => 'name?~John',
            'exception' => UnsupportedOperatorException::class,
        ],

        'unsupported_ilike_operator' => [
            'description' => 'ilike: operator uses ILIKE (PostgreSQL SQL), not valid DQL',
            'expression' => 'name?ilike:john',
            'exception' => UnsupportedOperatorException::class,
        ],

        'unsupported_ilike_autolike' => [
            'description' => '~~* (case-insensitive contains) aliases to ilike:, not valid DQL',
            'expression' => 'name?~~*john',
            'exception' => UnsupportedOperatorException::class,
        ],

        'unsupported_year_operator' => [
            'description' => 'year: operator uses YEAR() SQL function, not valid DQL',
            'expression' => 'date?year:2024',
            'exception' => UnsupportedOperatorException::class,
        ],

    ],
];
