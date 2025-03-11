# Derafu: Query - Expressive Path-Based Query Builder for PHP

![GitHub last commit](https://img.shields.io/github/last-commit/derafu/query/main)
![CI Workflow](https://github.com/derafu/query/actions/workflows/ci.yml/badge.svg?branch=main&event=push)
![GitHub code size in bytes](https://img.shields.io/github/languages/code-size/derafu/query)
![GitHub Issues](https://img.shields.io/github/issues-raw/derafu/query)
![Total Downloads](https://poser.pugx.org/derafu/query/downloads)
![Monthly Downloads](https://poser.pugx.org/derafu/query/d/monthly)

A flexible, intuitive PHP query builder that uses path expressions and configurable operators to simplify complex database queries and relationships.

## Why Derafu\Query?

### 🚀 **Intuitive Relationship Navigation**

Traditional query builders often:

- Require explicit join definitions.
- Make nested relationship queries verbose.
- Force you to understand the underlying join mechanics.
- Use different filter syntaxes across database engines.

### 🔥 **What Makes Derafu\Query Unique?**

| Feature                       | Derafu\Query    | Traditional Query Builders |
|-------------------------------|-----------------|----------------------------|
| **Path-Based Relationships**  | ✅ Yes          | ❌ No                      |
| **Automatic Join Resolution** | ✅ Yes          | ❌ No                      |
| **Configurable Operators**    | ✅ Yes          | ❌ No                      |
| **Framework Agnostic**        | ✅ Yes          | ⚠️ Varies                   |
| **Unified Filter Syntax**     | ✅ Yes          | ⚠️ Varies                   |
| **Multi-DB Compatibility**    | ✅ Yes          | ⚠️ Varies                   |

Derafu\Query is **not** a replacement for full-featured ORMs. Instead, it focuses on:

1. Providing an intuitive way to express complex relationships.
2. Offering a consistent filtering syntax across different backends.
3. Making query building more readable and maintainable.

---

## Features

- ✅ **Path-Based Relationships** – Express multi-table relationships in a clean, readable format.
- ✅ **Automatic Join Generation** – Let the query builder figure out the join conditions.
- ✅ **Rich Operator System** – 40+ operators for filtering with consistent syntax across databases.
- ✅ **YAML Configuration** – Easily extend and customize operators for your specific needs.
- ✅ **Multiple Backend Support** – Works with SQL databases today, with planned support for Eloquent, Doctrine, and more.
- ✅ **PHP 8+ Optimized** – Takes advantage of modern PHP features.
- ✅ **Framework Independent** – Use it in any PHP project.

---

## Installation

Install via Composer:

```bash
composer require derafu/query
```

## Basic Usage

```php
use Derafu\Query\Builder\SqlQueryBuilder;

// Create a query builder.
$queryBuilder = new SqlQueryBuilder($engine, $expressionParser);

// Build and execute a query with a simple condition with chained methods.
$result = $queryBuilder
    ->table('products')
    ->where('price?>1000')  // Price greater than 1000.
    ->execute();
```

## Path-Based Relationships

One of the most powerful features of Derafu\Query is the ability to express relationships through paths:

```php
// Find invoices with customer information.
$result = $queryBuilder
    ->where('invoices[alias:i]__customers[on:customer_id=id,alias:c]__name?isnot:null')
    ->execute();
```

The path syntax makes it clear which tables are being joined and on what conditions, all in a single expression.

The main advantage of this syntax is that it can be provided to end users in a simplified version that the backend later completes with the missing data to build filters. This is especially useful for filtering table listings through their columns.

The simplified filtering example in the invoices table seen above would look like this in the frontend:

```
name?isnot:null
```

With this filter applied to the invoices table on the customers table, the backend will complete the path with the current table (`invoices`) and the data for the join (`customers` and `customer_id=id`). This allows end users to easily write advanced filters across related tables.

## Rich Operator System

Derafu\Query includes a comprehensive set of operators for filtering data:

```php
// Standard comparison operators.
$queryBuilder->where('price?>500');                      // Greater than.
$queryBuilder->where('status?=active');                  // Equals.

// Pattern matching with automatic wildcard generation.
$queryBuilder->where('name?^John');                      // Starts with "John".
$queryBuilder->where('email?$*gmail.com');               // Ends with "gmail.com" (case-insensitive).
$queryBuilder->where('description?~~keyword');           // Contains "keyword".

// List operations.
$queryBuilder->where('status?in:active,pending');        // In list.
$queryBuilder->where('category?notin:archived,deleted'); // Not in list.

// Range queries.
$queryBuilder->where('price?between:10,50');             // Between 10 and 50.

// Date operations.
$queryBuilder->where('created_at?date:20240301');        // Specific date.
$queryBuilder->where('created_at?period:202403');        // Month and year.

// NULL checks.
$queryBuilder->where('deleted_at?is:null');              // Is NULL.
$queryBuilder->where('email?isnot:null');                // Is not NULL.

// Regular expressions.
$queryBuilder->where('name?~^J.*n$');                    // Regex match.

// Bitwise operations.
$queryBuilder->where('flags?b&1');                       // Bitwise AND.
```

## Configurable Operators

All operators are defined in a YAML configuration file, making it easy to extend or customize:

```yaml
operators:
    'between:':
        type: 'range'
        name: 'Between'
        description: 'Matches values between two specified values (inclusive).'
        pattern: '/^[\w\.\-\p{L}\p{M}]+,[\w\.\-\p{L}\p{M}]+$/u'
        cast: ['list']
        examples: ['between:1,10', 'between:2024-01-01,2024-12-31']
        sql: '{{column}} BETWEEN {{value_1}} AND {{value_2}}'
```

This allows you to:

- Add custom operators specific to your application.
- Adjust SQL templates for different database engines.
- Create aliases for frequently used operations.
- Document operators with examples for your team.

## Advanced Usage

### Complex Filtering

```php
// Find active products with price over 200.
$result = $queryBuilder
    ->table('products')
    ->where(['flags?b&1', 'price?>200'])
    ->execute();
```

### Join with Conditions

```php
// Find high-value invoices for company customers.
$result = $queryBuilder
    ->select('c.name, i.number, i.total')
    ->where([
        'customers[alias:c]__status?=active',
        'customers[alias:c]__invoices[on:id=customer_id,alias:i]__total?>1000'
    ])
    ->execute();
```

### Advanced Path Joins

```php
// Join with OR conditions.
$result = $queryBuilder
    $this->select('p.name, i.number')
    $this->where('products[alias:p]__category?=electronics')
    $this->orWhere('products[alias:p]__category?=software')
    $this->andWhere('products[alias:p]__invoice_details[on:id=product_id,alias:id]__invoices[on:invoice_id=id,alias:i]__number?isnot:null')
    ->execute();
```

### Cross-Database Compatibility

The same query syntax works across PostgreSQL, MySQL, SQLite, and other supported databases:

```php
// The same query syntax works on any database.
$result = $queryBuilder
    ->where('products__price?between:100,500')
    ->andWhere('products__name?~~*computer')          // Case-insensitive contains.
    ->andWhere('products__created_at?period:202401')  // January 2024.
    ->execute();
```

### Declarative Query Configuration

Beyond the fluent interface, Derafu\Query provides a powerful configuration-based approach to defining queries through the `QueryConfig` class:

```php
use Derafu\Query\Config\QueryConfig;

// Define a query using an array configuration.
$config = new QueryConfig([
    'table' => 'products',
    'select' => 'id, name, price',
    'where' => 'category?=electronics',
    'orderBy' => ['price' => 'DESC'],
    'limit' => 10
]);

// Apply the configuration to a query builder.
$result = $config->applyTo($queryBuilder)->execute();
```

## Operator Types

Derafu\Query includes a wide range of operator types:

- **Standard** - Direct SQL comparisons (`=`, `!=`, `>`, `<`, etc.).
- **AutoLike** - Automatic pattern generation for LIKE queries (`^`, `~~`, `$`, etc.).
- **Like** - Custom pattern matching (`like:`, `ilike:`).
- **List** - Multiple value operators (`in:`, `notin:`).
- **Range** - Value range operators (`between:`, `notbetween:`).
- **Date** - Date-specific operators (`date:`, `month:`, `year:`, `period:`).
- **Null** - NULL handling operators (`is:null`, `isnot:null`)
- **RegExp** - Regular expression operators (`~`, `~*`, `!~`, `!~*`).
- **Binary** - Bitwise operators (`b&`, `b|`, `b^`, etc.).

## Roadmap

- Support for Laravel's Eloquent.
- Integration with Doctrine ORM.
- Query caching mechanisms.
- Expanded filter operations.
- Performance optimizations.

## Performance Considerations

- Optimized for readable, maintainable query construction.
- Automatic join generation adds minimal overhead.
- For extremely performance-critical applications, consider pre-optimized raw queries.
- Configurable operators provide a balance between flexibility and performance.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request. For major changes, please open an issue first to discuss what you would like to change.

## License

This package is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
