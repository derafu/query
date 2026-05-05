<?php

declare(strict_types=1);

/**
 * Derafu: Query - Expressive Path-Based Query Builder for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Query\Builder\Sql;

use Derafu\Query\Builder\Contract\QueryBuilderWhereInterface;
use Derafu\Query\Builder\Contract\QueryInterface;
use Derafu\Query\Filter\Contract\CompositeConditionInterface;
use Derafu\Query\Filter\Contract\ConditionInterface;
use Derafu\Query\Filter\Contract\PathInterface;
use InvalidArgumentException;
use UnderflowException;

/**
 * SQL query builder implementation.
 *
 * This builder generates SQL queries from paths and filters, handling:
 *
 *   - SQL identifier quoting and escaping.
 *   - Value normalization and casting.
 *   - Multiple placeholder formats (single, paired, lists).
 *   - Different SQL templates per database engine.
 */
final class SqlBuilderWhere implements QueryBuilderWhereInterface
{
    use SqlSanitizerTrait;

    /**
     * Create a new SQL Query Builder Where.
     *
     * @param string $engine The database engine for SQL generation.
     * @param string $listDelimiter Delimiter used when processing list values.
     */
    public function __construct(
        private readonly string $engine = 'pgsql',
        private readonly string $listDelimiter = ','
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function build(
        ConditionInterface|CompositeConditionInterface $condition,
        string $parentAlias = ''
    ): QueryInterface {
        if ($condition instanceof ConditionInterface) {
            return $this->buildCondition($condition, $parentAlias);
        }

        return $this->buildCompositeCondition($condition, $parentAlias);
    }

    /**
     * Builds a query from a simple condition.
     *
     * @param ConditionInterface $condition
     * @param string $parentAlias Alias or name of the driving table for EXISTS paths.
     * @return QueryInterface The built where section of the query.
     */
    private function buildCondition(
        ConditionInterface $condition,
        string $parentAlias = ''
    ): QueryInterface {
        // EXISTS path: first segment carries subquery:exists.
        if ($condition->getPath()->getFirstSegment()->getOption('subquery') === 'exists') {
            return $this->buildExistsCondition($condition, $parentAlias);
        }

        // Get path and filter.
        $path = $condition->getPath();
        $filter = $condition->getFilter();

        // Get operator and base operator if exists.
        $operator = $filter->getOperator();
        $baseOperator = $operator->getBaseOperator();

        // Get SQL template for the engine.
        $templates = $operator->get('sql') ?? $baseOperator?->get('sql');
        if (is_string($templates)) {
            $sql = $templates;
        } else {
            $sql = $templates[$this->engine] ?? null;
        }

        if (empty($sql)) {
            throw new InvalidArgumentException(
                sprintf(
                    'No SQL template for operator %s on engine %s.',
                    $operator->getSymbol(),
                    $this->engine
                )
            );
        }

        // Get value.
        $value = $filter->getValue();

        // If the condition is literal, process the filter value and create the
        // parameters.
        if ($condition->isLiteral()) {

            // Validate value against pattern if exists.
            $pattern = $operator->getValidationPattern()
                ?? $baseOperator?->getValidationPattern()
            ;
            if ($pattern !== null && $value !== null) {
                if (!preg_match($pattern, $value)) {
                    throw new InvalidArgumentException(
                        sprintf(
                            'Invalid value format for operator %s: %s.',
                            $operator->getSymbol(),
                            $value
                        )
                    );
                }
            }

            // Normalize and cast value.
            $value = $this->normalizeValue($value);
            $value = $this->castValue(
                $value,
                (array)($operator->getCastingRules() ?: $baseOperator?->getCastingRules())
            );

            // Create parameters for the query.
            $parameters = $this->createParameters(
                $value,
                $this->sanitizeSqlSimpleIdentifier($path->getLastSegment()->getName())
            );

        }

        // Si la condición no es literal se reemplaza el valor sanitizado en la
        // plantilla SQL. No existen parámetros.
        else {
            if (str_contains($sql, '{{value}}')) {
                $sql = str_replace(
                    '{{value}}',
                    $this->sanitizeSqlIdentifier($value),
                    $sql
                );
            }

            $parameters = [];
        }

        // Build the WHERE clause.
        return new SqlQuery(...$this->createWhere(
            $sql,
            $this->buildColumnFromPath($path),
            $parameters
        ));
    }

    /**
     * Builds a query from a composite condition.
     *
     * @param CompositeConditionInterface $composite
     * @param string $parentAlias Alias or name of the driving table for EXISTS paths.
     * @return QueryInterface The built where section of the query.
     */
    private function buildCompositeCondition(
        CompositeConditionInterface $composite,
        string $parentAlias = ''
    ): QueryInterface {
        $conditions = [];
        $parameters = [];

        foreach ($composite->getConditions() as $condition) {
            $result = $this->build($condition, $parentAlias);

            $query = $result->getQuery();
            $conditions[] = $query['sql'];
            $parameters = array_merge($parameters, $query['parameters']);
        }

        $sql = '(' . implode(' ' . $composite->getType() . ' ', $conditions) . ')';

        return new SqlQuery($sql, $parameters);
    }

    /**
     * Normalizes a value ensuring it's a valid string for SQL.
     *
     * Handles:
     *
     *   - Boolean values to 1/0.
     *   - Arrays to delimited lists.
     *   - NULL values.
     *
     * @param mixed $value Raw value to normalize.
     * @return string|null Normalized string value.
     */
    private function normalizeValue(mixed $value): ?string
    {
        if (is_bool($value)) {
            return (string)(int)$value;
        }

        if (is_array($value)) {
            return implode($this->listDelimiter, array_map(
                fn ($v) => str_replace(
                    $this->listDelimiter,
                    '\\' . $this->listDelimiter,
                    (string)$v
                ),
                $value
            ));
        }

        return $value;
    }

    /**
     * Applies casting rules to a value.
     *
     * @param string|null $value The value to cast.
     * @param array $rules The casting rules to apply.
     * @return string|array|null The casted value.
     */
    private function castValue(?string $value, array $rules): string|array|null
    {
        if ($value === null || empty($rules)) {
            return $value;
        }

        foreach ($rules as $rule) {
            switch ($rule) {
                case 'like_start':
                    $value .= '%';
                    break;
                case 'like':
                    $value = '%' . $value . '%';
                    break;
                case 'like_end':
                    $value = '%' . $value;
                    break;
                case 'list':
                    $value = explode($this->listDelimiter, $value);
                    break;
                case 'date':
                    if (strlen($value) === 8) {
                        // YYYYMMDD to YYYY-MM-DD.
                        $value = substr($value, 0, 4) . '-'
                            . substr($value, 4, 2) . '-' . substr($value, 6, 2)
                        ;
                    } else {
                        // YYMMDD to YYYY-MM-DD (asume 20YY).
                        $value = '20' . substr($value, 0, 2) . '-'
                            . substr($value, 2, 2) . '-' . substr($value, 4, 2)
                        ;
                    }
                    break;

                case 'month':
                    // Asegura que el mes tenga 2 dígitos (8 -> 08)
                    $value = str_pad($value, 2, '0', STR_PAD_LEFT);
                    break;

                case 'year':
                    if (strlen($value) === 2) {
                        // YY to YYYY (asume 20YY).
                        $value = '20' . $value;
                    }
                    break;

                case 'period':
                    if (strlen($value) === 6) {
                        // YYYYMM se deja tal cual.
                        $value = $value;
                    } else {
                        // YYMM to YYYYMM (asume 20YY).
                        $value = '20' . $value;
                    }
                    break;
            }
        }

        return $value;
    }

    /**
     * Creates SQL parameters from a value.
     *
     * @param string|array|null $value The value to parameterize.
     * @param string $column The column name for parameter prefixing.
     * @return array The parameters array.
     */
    private function createParameters(string|array|null $value, string $column): array
    {
        $id = str_replace('.', '_', uniqid($column . '_'));
        $placeholder = 'param_' . $id;

        if (is_array($value)) {
            $parameters = [];
            foreach ($value as $i => $v) {
                $parameters[$placeholder . '_' . ($i + 1)] = $v;
            }
            return $parameters;
        }

        return [$placeholder => $value];
    }

    /**
     * Creates the WHERE clause from SQL template and parameters.
     *
     * Handles different placeholder formats:
     *
     *   - {{value}} for single values.
     *   - {{value_1}} and {{value_2}} for paired values.
     *   - {{values}} for lists.
     *
     * @param string $sql Base SQL template.
     * @param string $column The column name.
     * @param array $parameters Query parameters.
     * @return array{sql: string, parameters: array} The complete WHERE clause.
     */
    private function createWhere(string $sql, string $column, array $parameters): array
    {
        // Replace the column.
        $sql = str_replace('{{column}}', $column, $sql);

        // // No parameters needed for NULL operators.
        if (str_contains($sql, 'IS NULL') || str_contains($sql, 'IS NOT NULL')) {
            return [
                'sql' => $sql,
                'parameters' => [],
            ];
        }

        $parameterKeys = array_map(
            fn ($key) => ':' . $key,
            array_keys($parameters)
        );

        // Replace basic placeholders.
        $sql = strtr($sql, [
            '{{column}}' => $column,
            '{{operator}}' => '', // Not used in current implementation.
        ]);

        // Handle single value.
        if (str_contains($sql, '{{value}}')) {
            if (!isset($parameterKeys[0])) {
                throw new UnderflowException('Missing parameter for {{value}}.');
            }
            $sql = str_replace('{{value}}', $parameterKeys[0], $sql);
        }

        // Handle paired values.
        elseif (str_contains($sql, '{{value_1}}')) {
            if (!isset($parameterKeys[0], $parameterKeys[1])) {
                throw new UnderflowException(
                    'Missing parameters for {{value_1}} or {{value_2}}.'
                );
            }
            $sql = strtr($sql, [
                '{{value_1}}' => $parameterKeys[0],
                '{{value_2}}' => $parameterKeys[1],
            ]);
        }

        // Handle value lists.
        elseif (str_contains($sql, '{{values}}')) {
            if (empty($parameterKeys)) {
                throw new UnderflowException('Missing parameters for {{values}}.');
            }
            $sql = str_replace('{{values}}', implode(', ', $parameterKeys), $sql);
        }

        return [
            'sql' => $sql,
            'parameters' => $parameters,
        ];
    }

    /**
     * Builds a correlated EXISTS / NOT EXISTS subquery from an exists path.
     *
     * EXISTS paths start with ___ and carry a synthetic 'subquery' => 'exists'
     * option on every entry segment. The operator determines the wrapping:
     *   - 'is:empty'    → NOT EXISTS (no child record must match)
     *   - 'isnot:empty' → EXISTS     (at least one child record)
     *   - any other     → EXISTS with the operator applied to the column segment
     *
     * Nested ___ segments are joined flat inside the EXISTS subquery.
     *
     * @param ConditionInterface $condition The EXISTS condition.
     * @param string $parentAlias Alias or name of the outer driving table.
     */
    private function buildExistsCondition(
        ConditionInterface $condition,
        string $parentAlias
    ): QueryInterface {
        $segments  = $condition->getPath()->getSegments();
        $filter    = $condition->getFilter();
        $operator  = $filter->getOperator();
        $baseOp    = $operator->getBaseOperator();
        $effective = $baseOp ?? $operator;

        // Separate EXISTS entry segments from the optional column segment.
        $existsSegments = [];
        $columnSegment  = null;
        foreach ($segments as $segment) {
            if ($segment->getOption('subquery') === 'exists') {
                $existsSegments[] = $segment;
            } else {
                $columnSegment = $segment;
            }
        }

        // Operator determines EXISTS vs NOT EXISTS.
        $isNotExists  = ($effective->getSymbol() === 'is:empty');
        $existsPrefix = $isNotExists ? 'NOT EXISTS' : 'EXISTS';

        // Primary EXISTS table (first entry segment).
        $primary          = $existsSegments[0];
        $primaryTable     = $this->sanitizeSqlSimpleIdentifier($primary->getName());
        $primaryAlias     = $primary->getOption('alias');
        $primaryAliasOrName = $primaryAlias ?? $primary->getName();
        $subqueryFrom     = $primaryAlias !== null
            ? $primaryTable . ' AS ' . $this->sanitizeSqlSimpleIdentifier($primaryAlias)
            : $primaryTable;

        // Correlation condition: parentAlias.col = primaryTable.col (from on: option).
        $whereParts = [];
        foreach ($primary->getOption('on', []) as $parentCol => $childCol) {
            $whereParts[] = sprintf(
                '%s.%s = %s.%s',
                $this->sanitizeSqlSimpleIdentifier($parentAlias),
                $this->sanitizeSqlSimpleIdentifier($parentCol),
                $this->sanitizeSqlSimpleIdentifier($primaryAliasOrName),
                $this->sanitizeSqlSimpleIdentifier($childCol)
            );
        }

        // JOINs for nested EXISTS segments (e.g. ___payments___items).
        $joins              = '';
        $prevAliasOrName    = $primaryAliasOrName;
        $lastExistsAlias    = $primaryAliasOrName;

        for ($i = 1; $i < count($existsSegments); $i++) {
            $seg           = $existsSegments[$i];
            $segTable      = $this->sanitizeSqlSimpleIdentifier($seg->getName());
            $segAlias      = $seg->getOption('alias');
            $segAliasOrName = $segAlias ?? $seg->getName();
            $segRef        = $segAlias !== null
                ? $segTable . ' AS ' . $this->sanitizeSqlSimpleIdentifier($segAlias)
                : $segTable;

            $joinParts = [];
            foreach ($seg->getOption('on', []) as $prevCol => $segCol) {
                $joinParts[] = sprintf(
                    '%s.%s = %s.%s',
                    $this->sanitizeSqlSimpleIdentifier($prevAliasOrName),
                    $this->sanitizeSqlSimpleIdentifier($prevCol),
                    $this->sanitizeSqlSimpleIdentifier($segAliasOrName),
                    $this->sanitizeSqlSimpleIdentifier($segCol)
                );
            }

            $joins .= ' JOIN ' . $segRef;
            if (!empty($joinParts)) {
                $joins .= ' ON ' . implode(' AND ', $joinParts);
            }

            $prevAliasOrName = $segAliasOrName;
            $lastExistsAlias = $segAliasOrName;
        }

        // Aggregate scalar subquery: ___table__AGG(col)?op:value
        if (
            $columnSegment !== null
            && preg_match('/^(SUM|AVG|COUNT|MIN|MAX)\s*\((.+)\)$/i', $columnSegment->getName(), $m)
        ) {
            return $this->buildAggregateSubquery(
                $condition,
                $parentAlias,
                $primary,
                strtoupper($m[1]),
                trim($m[2])
            );
        }

        // Column filter inside the EXISTS subquery (e.g. ___payments__status?=pending).
        $parameters = [];

        if ($columnSegment !== null) {
            $templates   = $operator->get('sql') ?? $baseOp?->get('sql');
            $filterSql   = is_string($templates) ? $templates : ($templates[$this->engine] ?? null);

            if (!empty($filterSql)) {
                $qualifiedColumn = $this->sanitizeSqlIdentifier(
                    $lastExistsAlias . '.' . $columnSegment->getName()
                );

                $value = $this->normalizeValue($filter->getValue());
                $value = $this->castValue(
                    $value,
                    (array)($operator->getCastingRules() ?: $baseOp?->getCastingRules())
                );

                $parameters   = $this->createParameters($value, $columnSegment->getName());
                $innerResult  = $this->createWhere($filterSql, $qualifiedColumn, $parameters);
                $whereParts[] = $innerResult['sql'];
                $parameters   = $innerResult['parameters'];
            }
        }

        $subquery = 'SELECT 1 FROM ' . $subqueryFrom . $joins;
        if (!empty($whereParts)) {
            $subquery .= ' WHERE ' . implode(' AND ', $whereParts);
        }

        return new SqlQuery($existsPrefix . ' (' . $subquery . ')', $parameters);
    }

    /**
     * Builds a scalar aggregate subquery condition.
     *
     * Produces: (SELECT AGG(alias.col) FROM table alias WHERE correlation) op :param
     *
     * @param ConditionInterface $condition The aggregate condition.
     * @param string $parentAlias Alias or name of the driving table (outer query).
     * @param object $primary The first EXISTS segment (table + options).
     * @param string $funcName Whitelisted aggregate function name (SUM, AVG, etc.).
     * @param string $funcArg Raw argument from path (e.g. "amount" or "*").
     */
    private function buildAggregateSubquery(
        ConditionInterface $condition,
        string $parentAlias,
        object $primary,
        string $funcName,
        string $funcArg
    ): QueryInterface {
        $filter    = $condition->getFilter();
        $operator  = $filter->getOperator();
        $baseOp    = $operator->getBaseOperator();
        $effective = $baseOp ?? $operator;

        $primaryTable = $this->sanitizeSqlSimpleIdentifier($primary->getName());
        $subAlias     = $primary->getOption('alias') ?? strtolower($primary->getName()[0]);

        $subqueryFrom = $primaryTable . ' ' . $this->sanitizeSqlSimpleIdentifier($subAlias);

        // Rebuild correlation using the resolved subAlias (explicit alias may differ from auto).
        $whereParts = [];
        foreach ($primary->getOption('on', []) as $parentCol => $childCol) {
            $whereParts[] = sprintf(
                '%s.%s = %s.%s',
                $this->sanitizeSqlSimpleIdentifier($parentAlias),
                $this->sanitizeSqlSimpleIdentifier($parentCol),
                $this->sanitizeSqlSimpleIdentifier($subAlias),
                $this->sanitizeSqlSimpleIdentifier($childCol)
            );
        }

        // COUNT(*) = 0 / > 0 → rewrite to NOT EXISTS / EXISTS (avoids full aggregate scan).
        if ($funcArg === '*') {
            $existsPrefix = $this->resolveCountStarToExistsPrefix($effective, $filter->getValue());
            if ($existsPrefix !== null) {
                $inner = 'SELECT 1 FROM ' . $subqueryFrom;
                if (!empty($whereParts)) {
                    $inner .= ' WHERE ' . implode(' AND ', $whereParts);
                }
                return new SqlQuery($existsPrefix . ' (' . $inner . ')', []);
            }
        }

        // Build aggregate expression; * is kept as-is to support COUNT(*).
        if ($funcArg === '*') {
            $aggExpr = $funcName . '(*)';
        } else {
            $aggExpr = $funcName . '(' . $this->sanitizeSqlSimpleIdentifier($subAlias)
                . '.' . $this->sanitizeSqlSimpleIdentifier($funcArg) . ')';
        }

        $subquery = '(SELECT ' . $aggExpr . ' FROM ' . $subqueryFrom;
        if (!empty($whereParts)) {
            $subquery .= ' WHERE ' . implode(' AND ', $whereParts);
        }
        $subquery .= ')';

        // Get comparison operator SQL template.
        $templates = $operator->get('sql') ?? $baseOp?->get('sql');
        $filterSql = is_string($templates) ? $templates : ($templates[$this->engine] ?? null);

        if (empty($filterSql)) {
            throw new InvalidArgumentException(sprintf(
                'No SQL template for operator %s on engine %s.',
                $operator->getSymbol(),
                $this->engine
            ));
        }

        // Create parameters from the filter value.
        $value      = $this->normalizeValue($filter->getValue());
        $value      = $this->castValue(
            $value,
            (array)($operator->getCastingRules() ?: $baseOp?->getCastingRules())
        );
        $parameters = $this->createParameters($value, $funcName);

        return new SqlQuery(...$this->createWhere($filterSql, $subquery, $parameters));
    }

    /**
     * Maps COUNT(*) existence conditions to EXISTS / NOT EXISTS.
     *
     * Returns the SQL prefix ('EXISTS' or 'NOT EXISTS') when the operator and
     * value form a pure existence check, or null when the condition is not
     * rewritable (e.g. COUNT(*) > 5 must remain a scalar aggregate subquery).
     */
    private function resolveCountStarToExistsPrefix(object $effective, mixed $value): ?string
    {
        $symbol = $effective->getSymbol();
        $v      = trim((string)($value ?? ''));

        return match (true) {
            $symbol === 'is:empty'                   => 'NOT EXISTS',
            $symbol === 'isnot:empty'                => 'EXISTS',
            $symbol === '='  && $v === '0'           => 'NOT EXISTS',
            $symbol === '!=' && $v === '0'           => 'EXISTS',
            $symbol === '>'  && $v === '0'           => 'EXISTS',
            $symbol === '>=' && $v === '1'           => 'EXISTS',
            $symbol === '<'  && $v === '1'           => 'NOT EXISTS',
            $symbol === '<=' && $v === '0'           => 'NOT EXISTS',
            default                                  => null,
        };
    }

    /**
     * Builds a fully qualified column name from a path.
     *
     * @param PathInterface $path The path to convert to a column name.
     * @return string The sanitized column name.
     */
    private function buildColumnFromPath(PathInterface $path): string
    {
        $segments = $path->getSegments();
        $lastSegment = end($segments);
        $column = $lastSegment->getName();

        // Check if the column is a function (has parentheses).
        $isFunction = preg_match('/^([A-Za-z0-9_]+)\s*\((.*)\)$/', $column, $matches);

        // If it's a function like AVG(price) or TO_CHAR(date, 'YYYY-MM-DD').
        if ($isFunction) {
            $functionName = $matches[1]; // For example, "AVG" or "TO_CHAR".
            $functionArgs = $matches[2]; // For example, "price" or "date, 'YYYY-MM-DD'".

            // If there are previous segments, we need to qualify the columns in
            // the arguments.
            if (count($segments) > 1) {
                $parentIndex = count($segments) - 2;
                $parent = $segments[$parentIndex];
                $tablePrefix = $parent->getOption('alias') ?? $parent->getName();

                // Split the arguments, respecting parentheses and quotes.
                $args = $this->splitFunctionArguments($functionArgs);

                // Qualify each argument if it appears to be a column name.
                $qualifiedArgs = [];
                foreach ($args as $arg) {
                    $arg = trim($arg);

                    // If the argument appears to be a column name (not a
                    // literal or complex expression).
                    if (!$this->isLiteralOrExpression($arg)) {
                        $qualifiedArgs[] = $tablePrefix . '.' . $arg;
                    } else {
                        $qualifiedArgs[] = $arg;
                    }
                }

                // Rebuild the function with qualified arguments.
                $qualifiedColumn = $functionName . '(' . implode(', ', $qualifiedArgs) . ')';

                return $this->sanitizeSqlIdentifier($qualifiedColumn);
            }

            // If there are no previous segments, use as is.
            return $this->sanitizeSqlIdentifier($column);
        }

        // If it's not a function, proceed with the normal logic.
        if (count($segments) > 1) {
            $parentIndex = count($segments) - 2;
            $parent = $segments[$parentIndex];
            $tablePrefix = $parent->getOption('alias') ?? $parent->getName();
            return $this->sanitizeSqlIdentifier($tablePrefix . '.' . $column);
        }

        return $this->sanitizeSqlIdentifier($column);
    }

    /**
     * Splits function arguments respecting nested parentheses and quotes.
     *
     * @param string $argsString The function arguments as a string.
     * @return array<string> The separated arguments.
     */
    private function splitFunctionArguments(string $argsString): array
    {
        if (empty($argsString)) {
            return [];
        }

        $args = [];
        $current = '';
        $parenCount = 0;
        $inQuote = false;
        $quoteChar = '';

        for ($i = 0; $i < strlen($argsString); $i++) {
            $char = $argsString[$i];

            // Handle quotes.
            if (($char === "'" || $char === '"') && ($i === 0 || $argsString[$i - 1] !== '\\')) {
                if (!$inQuote) {
                    $inQuote = true;
                    $quoteChar = $char;
                } elseif ($char === $quoteChar) {
                    $inQuote = false;
                }
            }

            // Handle parentheses.
            if (!$inQuote) {
                if ($char === '(') {
                    $parenCount++;
                } elseif ($char === ')') {
                    $parenCount--;
                }
            }

            // If we find a comma outside of quotes and parentheses, separate
            // the argument.
            if ($char === ',' && !$inQuote && $parenCount === 0) {
                $args[] = $current;
                $current = '';
                continue;
            }

            $current .= $char;
        }

        // Add the last argument.
        if (!empty($current)) {
            $args[] = $current;
        }

        return $args;
    }

    /**
     * Determines if a string is a literal or a complex expression.
     *
     * @param string $arg The argument to check.
     * @return bool True if it's a literal or complex expression.
     */
    private function isLiteralOrExpression(string $arg): bool
    {
        // Single or double quotes indicate a string literal.
        if (preg_match('/^[\'\"].*[\'\"]$/', $arg)) {
            return true;
        }

        // Number (integer or decimal).
        if (is_numeric($arg)) {
            return true;
        }

        // Expressions with arithmetic operators.
        if (preg_match('/[\+\-\*\/]/', $arg)) {
            return true;
        }

        // Nested functions.
        if (preg_match('/[a-zA-Z0-9_]+\s*\(.*\)/', $arg)) {
            return true;
        }

        // Wildcards and special constants.
        if (
            $arg === '*'
            || strtoupper($arg) === 'NULL'
            || strtoupper($arg) === 'TRUE'
            || strtoupper($arg) === 'FALSE'
        ) {
            return true;
        }

        return false;
    }
}
