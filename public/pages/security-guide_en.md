# Security Guide for SqlSanitizerTrait

This document provides guidelines on the secure use of `SqlSanitizerTrait` to prevent SQL injections.

## Basic Principles

The `SqlSanitizerTrait` is designed to sanitize SQL identifiers (table names, columns, etc.) and SQL expressions of medium complexity. Although it provides protection against many forms of SQL injection, it's important to understand its limitations and use cases.

## Safe Usage

### ✅ Recommended Use Cases

- **Column and Table Names**:
  ```php
  $query->select('column_name');
  ```

- **Qualified Columns**:
  ```php
  $query->select('table.column');
  ```

- **Expressions with Aliases**:
  ```php
  $query->select('column AS alias');
  ```

- **Standard Aggregation Functions**:
  ```php
  $query->select('COUNT(*) AS total');
  ```

### ⚠️ Cases Requiring Special Attention

- **Complex SQL Functions**: Ensure that arguments are properly sanitized.
  ```php
  $query->select('COALESCE(column1, column2, 0) AS result');
  ```

- **Expressions with Arithmetic Operators**: May work but verify the results.
  ```php
  $query->select('price * quantity AS total');
  ```

## Specific Prevention of SQL Injections

The sanitizer cannot guarantee absolute security if dynamic SQL is executed with concatenation instead of prepared statements.

### Parameters vs. Identifiers

It's important to understand the distinction:

- **Parameters** (values): Must be passed through prepared statements, not sanitized with this trait.
- **Identifiers** (column/table names): Should be sanitized with this trait.

## Additional Best Practices

1. **Apply the principle of least privilege** for database connections.
2. **Use whitelists** for valid column and table names.
3. **Keep database components updated**.
4. **Log and monitor** unusual queries or database errors.
5. **Regularly test** with security scanning tools.
