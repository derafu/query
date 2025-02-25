## Declarative Query Configuration

Beyond the fluent interface, Derafu\Query provides a powerful configuration-based approach to defining queries through the `QueryConfig` class:

```php
use Derafu\Query\Config\QueryConfig;

// Define a query using an array configuration.
$config = new QueryConfig([
    'table' => 'products',
    'select' => 'id, name, price',
    'where' => 'category?=electronics',
    'orderBy' => ['price' => 'DESC'],
    'limit' => 10,
]);

// Apply the configuration to a query builder.
$result = $config->applyTo($queryBuilder)->execute();
```

This declarative approach offers several advantages.

### Configuration from Multiple Sources

Load query definitions from various formats:

```php
// From YAML files.
$config = QueryConfig::fromYamlFile('queries/product_report.yaml');

// From JSON files.
$config = QueryConfig::fromJsonFile('queries/sales_analysis.json');

// From strings.
$config = QueryConfig::fromYamlString($yamlContent);
$config = QueryConfig::fromJsonString($jsonContent);

// With automatic format detection.
$config = QueryConfig::fromFile('queries/user_stats.yaml');
```

### API-Driven Queries

This approach is particularly useful for building dynamic queries from API requests:

```php
// Receive a query definition from an API request.
$requestData = $request->getJsonBody();

// Create a secure query from the request data.
$config = new QueryConfig([
    'table' => 'products',
    'where' => $requestData['filters'] ?? [],
    'orderBy' => $requestData['sort'] ?? ['id' => 'ASC'],
    'limit' => min($requestData['limit'] ?? 20, 100),
    'offset' => $requestData['offset'] ?? 0,
]);

// Execute the query.
$result = $config->applyTo($queryBuilder)->execute();
```

### Reusable Query Templates

Store common query patterns as configuration files:

```yaml
# recent_products.yaml
table: products
select: id, name, price, created_at
where: deleted_at?is:null
orderBy:
    created_at: DESC
limit: 20
```

```php
// Load and customize the template.
$config = QueryConfig::fromYamlFile('templates/recent_products.yaml');
$builder = $config->applyTo($queryBuilder);

// Add additional conditions.
if ($category) {
    $builder->andWhere('category?=' . $category);
}

$result = $builder->execute();
```

### Complete Configuration Options

The configuration supports all query builder features:

```php
$config = new QueryConfig([
    // Basic query parts.
    'table' => 'invoices',
    'alias' => 'i',
    'select' => 'i.id, i.number, c.name AS customer_name',
    'distinct' => true,

    // WHERE conditions.
    'where' => 'i.status?=paid',
    'andWhere' => 'i.total?>1000',
    'orWhere' => 'i.date?period:202403',
    'andWhereOr' => [
        ['i.category?=service', 'i.total?>500'],
        ['i.category?=product', 'i.total?>1000'],
    ],

    // JOINs.
    'innerJoin' => [
        'table' => 'customers',
        'alias' => 'c',
        'condition' => 'i.customer_id = c.id',
    ],

    // Grouping & sorting.
    'groupBy' => ['i.status'],
    'having' => 'COUNT(*)?>1',
    'orderBy' => ['i.created_at' => 'DESC'],

    // Pagination.
    'limit' => 20,
    'offset' => 40,
]);
```

This declarative approach complements the fluent interface, giving you flexibility in how you define and manage your queries.
