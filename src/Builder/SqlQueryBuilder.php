<?php

declare(strict_types=1);

/**
 * Derafu: Query - Expressive Path-Based Query Builder for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Query\Builder;

use Derafu\Query\Builder\Contract\QueryBuilderInterface;
use Derafu\Query\Builder\Contract\QueryInterface;
use Derafu\Query\Builder\Sql\SqlBuilderWhere;
use Derafu\Query\Builder\Sql\SqlQuery;
use Derafu\Query\Builder\Sql\SqlSanitizerTrait;
use Derafu\Query\Engine\Contract\SqlEngineInterface;
use Derafu\Query\Filter\CompositeCondition;
use Derafu\Query\Filter\Contract\CompositeConditionInterface;
use Derafu\Query\Filter\Contract\ConditionInterface;
use Derafu\Query\Filter\Contract\ExpressionParserInterface;
use Derafu\Query\Filter\Contract\PathInterface;
use InvalidArgumentException;
use RuntimeException;

/**
 * SQL implementation of the query builder.
 *
 * This builder generates SQL queries. It translates string filter expressions
 * into SQL using the expression parsers.
 */
final class SqlQueryBuilder implements QueryBuilderInterface
{
    use SqlSanitizerTrait;

    /**
     * The columns to select.
     *
     * @var array<string>
     */
    private array $columns = ['*'];

    /**
     * The base table name.
     *
     * @var string|null
     */
    private ?string $table = null;

    /**
     * The table alias if any.
     *
     * @var string|null
     */
    private ?string $alias = null;

    /**
     * The where conditions (always as a composite condition).
     *
     * @var CompositeConditionInterface
     */
    private CompositeConditionInterface $where;

    /**
     * Maximum number of records to return.
     *
     * @var int|null
     */
    private ?int $limit = null;

    /**
     * Number of records to skip.
     *
     * @var int|null
     */
    private ?int $offset = null;

    /**
     * Order by clauses for the query.
     *
     * @var array<array{0: string, 1: string}>|null
     */
    private ?array $orderBy = null;

    /**
     * Group by columns.
     *
     * @var array<string>
     */
    private array $groupBy = [];

    /**
     * Having conditions for grouped results.
     *
     * @var CompositeConditionInterface|null
     */
    private ?CompositeConditionInterface $having = null;

    /**
     * Whether to perform a DISTINCT selection.
     *
     * @var bool
     */
    private bool $distinct = false;

    /**
     * Join clauses for the query.
     *
     * @var array<string,array{table: string, condition: string|null, type: string, alias: string|null}>
     */
    private array $joins = [];

    /**
     * Create a new SQL Query Builder.
     *
     * @param SqlEngineInterface $engine
     * @param ExpressionParserInterface $expressionParser
     */
    public function __construct(
        private readonly SqlEngineInterface $engine,
        private readonly ExpressionParserInterface $expressionParser
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function new(): self
    {
        return clone $this;
    }

    /**
     * {@inheritDoc}
     */
    public function table(string $table, ?string $alias = null): self
    {
        return $this->new()->from($table, $alias);
    }

    /**
     * {@inheritDoc}
     */
    public function select(string|array $columns, bool $sanitize = true): self
    {
        if (is_string($columns)) {
            $this->columns = array_map('trim', explode(',', $columns));
        } elseif (is_array($columns)) {
            $this->columns = $columns;
        }

        if ($sanitize) {
            $this->columns = array_map(
                [$this, 'sanitizeSqlIdentifier'],
                $this->columns
            );
        }

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function from(string $table, ?string $alias = null): self
    {
        $this->table = $this->sanitizeSqlSimpleIdentifier($table);

        if ($alias !== null) {
            $this->alias = $this->sanitizeSqlSimpleIdentifier($alias);
        } else {
            $this->alias = null;
        }

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function where(
        string|array|ConditionInterface|CompositeConditionInterface $condition
    ): self {
        $this->where = CompositeCondition::and();
        $this->addConditions($this->where, $condition);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function andWhere(
        string|array|ConditionInterface|CompositeConditionInterface $condition
    ): self {
        if (!isset($this->where)) {
            return $this->where($condition);
        }

        $this->addConditions($this->where, $condition);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function orWhere(
        string|array|ConditionInterface|CompositeConditionInterface $condition
    ): self {
        if (!isset($this->where)) {
            return $this->where($condition);
        }

        // Save the existing WHERE condition.
        $existingWhere = $this->where;

        // Create a new OR composite as root.
        $this->where = CompositeCondition::or();

        // Add the existing condition to the OR composite.
        $this->where->add($existingWhere);

        // Process the new condition.
        if (is_array($condition)) {
            // Check if this array has sub-arrays (multiple OR groups).
            $hasSubArrays = false;
            foreach ($condition as $item) {
                if (is_array($item)) {
                    $hasSubArrays = true;
                    break;
                }
            }

            if ($hasSubArrays) {
                // Each item is a separate OR group.
                foreach ($condition as $item) {
                    if (is_array($item)) {
                        // Create an AND group for this sub-array.
                        $and = CompositeCondition::and();
                        $this->addConditions($and, $item);
                        $this->where->add($and);
                    } else {
                        // Single condition becomes its own AND group.
                        $singleAnd = CompositeCondition::and();
                        $this->addConditions($singleAnd, $item);
                        $this->where->add($singleAnd);
                    }
                }
            } else {
                // Entire array is one AND group.
                $and = CompositeCondition::and();
                $this->addConditions($and, $condition);
                $this->where->add($and);
            }
        } elseif ($condition instanceof CompositeConditionInterface) {
            $this->where->add($condition);
        } else {
            $and = CompositeCondition::and();
            $this->addConditions($and, $condition);
            $this->where->add($and);
        }

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function andWhereOr(
        array|ConditionInterface|CompositeConditionInterface $conditions
    ): self {
        if (!isset($this->where)) {
            $this->where = CompositeCondition::and();
        }

        // Create a new OR composite.
        $or = CompositeCondition::or();

        // Process each element of the array as a separate AND group.
        if (is_array($conditions)) {
            foreach ($conditions as $groupConditions) {
                if (!is_array($groupConditions)) {
                    // If not an array, treat as simple condition.
                    $and = CompositeCondition::and();
                    $this->addConditions($and, $groupConditions);
                    $or->add($and);
                } else {
                    // Create an AND group for this subarray.
                    $and = CompositeCondition::and();
                    $this->addConditions($and, $groupConditions);
                    $or->add($and);
                }
            }
        } else {
            // If not an array, add directly.
            $or->add($conditions);
        }

        // Add the OR composite to the main AND composite.
        $this->where->add($or);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function limit(int $limit): self
    {
        $this->limit = $limit;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function offset(int $offset): self
    {
        $this->offset = $offset;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function orderBy(string|array $columns, string $direction = 'ASC'): self
    {
        if (is_string($columns)) {
            $orderBy = [[$columns, $direction]];
        } elseif (is_array($columns)) {
            $orderBy = [];
            foreach ($columns as $column => $dir) {
                if (is_int($column)) {
                    // Handle ['column1', 'column2'] format.
                    $orderBy[] = [$dir, 'ASC'];
                } else {
                    // Handle ['column1' => 'DESC', 'column2' => 'ASC'] format.
                    $orderBy[] = [$column, $dir];
                }
            }
        }

        $this->orderBy = [];
        foreach ($orderBy as $ob) {
            $this->orderBy[] = [
                $this->sanitizeSqlIdentifier($ob[0]),
                strtoupper($this->sanitizeSqlIdentifier($ob[1])),
            ];
        }

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function groupBy(string|array $columns): self
    {
        if (is_string($columns)) {
            $columns = [$columns];
        }

        $columns = array_map([$this, 'sanitizeSqlIdentifier'], $columns);
        $this->groupBy = array_merge($this->groupBy, $columns);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function having(
        string|array|ConditionInterface|CompositeConditionInterface $condition
    ): self {
        $this->having = CompositeCondition::and();
        $this->addConditions($this->having, $condition);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function distinct(bool $distinct = true): self
    {
        $this->distinct = $distinct;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function join(
        string $table,
        string $condition,
        string $type = 'INNER',
        ?string $alias = null
    ): self {
        if (!isset($this->joins[$table])) {
            $type = strtoupper($type);
            if (!in_array($type, ['INNER', 'LEFT', 'RIGHT', 'CROSS'])) {
                throw new InvalidArgumentException(sprintf(
                    'Invalid join type %s.',
                    $type
                ));
            }

            $id = $table . ':' . ($alias ?? $table);

            $this->joins[$id] = [
                'table' => $this->sanitizeSqlSimpleIdentifier($table),
                'condition' => $condition, // The condition is not sanitized, it must be safe!.
                'type' => $type,
                'alias' => $alias !== null
                    ? $this->sanitizeSqlSimpleIdentifier($alias)
                    : null
                ,
            ];
        }

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function leftJoin(string $table, string $condition, ?string $alias = null): self
    {
        return $this->join($table, $condition, 'LEFT', $alias);
    }

    /**
     * {@inheritDoc}
     */
    public function rightJoin(string $table, string $condition, ?string $alias = null): self
    {
        return $this->join($table, $condition, 'RIGHT', $alias);
    }

    /**
     * {@inheritDoc}
     */
    public function innerJoin(string $table, string $condition, ?string $alias = null): self
    {
        return $this->join($table, $condition, 'INNER', $alias);
    }

    /**
     * {@inheritDoc}
     */
    public function crossJoin(string $table, ?string $alias = null): self
    {
        return $this->join($table, '', 'CROSS', $alias);
    }

    /**
     * {@inheritDoc}
     */
    public function getQuery(): QueryInterface
    {
        // Process paths, get base table and apply joins if they exist.
        $this->processPathsForFrom();

        if (!isset($this->table)) {
            throw new RuntimeException('No table specified for query.');
        }

        // Build SELECT clause.
        $sql = 'SELECT ';
        if (isset($this->distinct) && $this->distinct) {
            $sql .= 'DISTINCT ';
        }
        $sql .= implode(', ', $this->columns);

        // Build FROM clause.
        $sql .= ' FROM ' . $this->table;
        if (isset($this->alias)) {
            $sql .= ' AS ' . $this->alias;
        }

        // Build JOIN clauses.
        if (!empty($this->joins)) {
            foreach ($this->joins as $join) {
                $sql .= ' ' . $join['type'] . ' JOIN ' . $join['table'];
                if ($join['alias']) {
                    $sql .= ' AS ' . $join['alias'];
                }
                if ($join['condition']) {
                    $sql .= ' ON ' . $join['condition'];
                }
            }
        }

        $parameters = [];

        // Build WHERE clause.
        if (isset($this->where)) {
            $conditionBuilder = new SqlBuilderWhere($this->engine->getDriver());
            $result = $conditionBuilder->build($this->where)->getQuery();
            $sql .= ' WHERE ' . $result['sql'];
            $parameters = $result['parameters'];
        }

        // Build GROUP BY clause.
        if (isset($this->groupBy) && !empty($this->groupBy)) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groupBy);
        }

        // Build HAVING clause.
        if (isset($this->having)) {
            $conditionBuilder = new SqlBuilderWhere($this->engine->getDriver());
            $result = $conditionBuilder->build($this->having)->getQuery();
            $sql .= ' HAVING ' . $result['sql'];
            $parameters = array_merge($parameters, $result['parameters']);
        }

        // Build ORDER BY clause.
        if (isset($this->orderBy) && !empty($this->orderBy)) {
            $orderClauses = [];
            foreach ($this->orderBy as [$column, $direction]) {
                $orderClauses[] = $column . ' ' . $direction;
            }
            $sql .= ' ORDER BY ' . implode(', ', $orderClauses);
        }

        // Build LIMIT and OFFSET.
        if (isset($this->limit)) {
            $sql .= ' LIMIT ' . $this->limit;
            if (isset($this->offset)) {
                $sql .= ' OFFSET ' . $this->offset;
            }
        }

        return new SqlQuery($sql, $parameters);
    }

    /**
     * {@inheritDoc}
     */
    public function execute(): array
    {
        $query = $this->getQuery();

        return $this->engine->execute($query['sql'], $query['parameters']);
    }

    /**
     * Adds conditions to a composite condition.
     *
     * This method handles various input types:
     *
     *   - String expressions: Parsed into conditions using the expression parser.
     *   - Arrays of expressions: Each is parsed and added to the composite.
     *   - ConditionInterface objects: Added directly to the composite.
     *   - CompositeConditionInterface objects: Added directly to the composite.
     *
     * @param CompositeConditionInterface $composite The composite to add conditions to.
     * @param string|array|ConditionInterface|CompositeConditionInterface $conditions
     * The condition expression(s) to add.
     * @return void
     */
    private function addConditions(
        CompositeConditionInterface $composite,
        string|array|ConditionInterface|CompositeConditionInterface $conditions
    ): void {
        // Handle direct ConditionInterface object.
        if ($conditions instanceof ConditionInterface) {
            $composite->add($conditions);
            return;
        }

        // Handle direct CompositeConditionInterface object.
        if ($conditions instanceof CompositeConditionInterface) {
            $composite->add($conditions);
            return;
        }

        // Convert single string to array for consistent handling.
        if (is_string($conditions)) {
            $conditions = [$conditions];
        }

        // Process array of expressions.
        foreach ($conditions as $condition) {
            if ($condition instanceof ConditionInterface ||
                $condition instanceof CompositeConditionInterface) {
                $composite->add($condition);
            } else {
                if (is_array($condition)) {
                    $this->addConditions($composite, $condition);
                } else {
                    $composite->add($this->expressionParser->parse($condition));
                }
            }
        }
    }

    /**
     * Processes all query conditions to extract paths, detect the base table
     * and apply joins.
     */
    private function processPathsForFrom(): void
    {
        if (!isset($this->where)) {
            return;
        }

        // Extract all paths from conditions.
        $paths = $this->extractPathsFromConditions($this->where);

        // If there is no table defined yet and there are paths, use the table
        // from the first path if at least two segments exists.
        if (!isset($this->table) && !empty($paths)) {
            $firstPath = reset($paths);
            $segments = $firstPath->getSegments();
            if (isset($segments[1])) {
                $firstSegment = $segments[0];

                $this->from(
                    $firstSegment->getName(),
                    $firstSegment->getOption('alias')
                );
            }
        }

        // Apply joins to all routes.
        foreach ($paths as $path) {
            $this->applyJoinsFromPath($path);
        }
    }

    /**
     * Extracts all paths from a condition or compound condition.
     *
     * @param ConditionInterface|CompositeConditionInterface $condition
     * @return array<PathInterface>
     */
    private function extractPathsFromConditions($condition): array
    {
        $paths = [];

        if ($condition instanceof ConditionInterface) {
            $paths[] = $condition->getPath();
        } else {
            // It is a compound condition, process recursively.
            foreach ($condition->getConditions() as $subCondition) {
                $paths = array_merge(
                    $paths,
                    $this->extractPathsFromConditions($subCondition)
                );
            }
        }

        return $paths;
    }

    /**
     * Applies joins based on a path.
     *
     * @param PathInterface $path
     */
    private function applyJoinsFromPath(PathInterface $path): void
    {
        $segments = $path->getSegments();

        // If there is only one segment, there are no joins to make.
        if (count($segments) <= 1) {
            return;
        }

        // The first segment should be the base table.
        $baseSegment = $segments[0];
        $baseTable = $baseSegment->getName();
        $baseAlias = $baseSegment->getOption('alias');

        // Check that it matches the current table.
        // If the base table of the path does not match the one in the query, it
        // is not appropriate to process the paths as JOINS (because it is not
        // appropriate or because the query is malformed).
        if ($this->table !== $baseTable) {
            return;
        }

        // Process intermediate segments (excluding the first and last).
        $previousTable = $baseTable;
        $previousAlias = $baseAlias;

        for ($i = 1; $i < count($segments) - 1; $i++) {
            $segment = $segments[$i];
            $targetTable = $segment->getName();
            $targetAlias = $segment->getOption('alias');

            // Determine join type.
            $joinType = strtoupper($segment->getOption('join', 'INNER'));

            // Check join conditions.
            $joinConditions = $segment->getOption('on');
            if ($joinConditions) {
                $joinConditionParts = [];

                foreach ($joinConditions as $sourceCol => $targetCol) {
                    $sourceRef = $previousAlias ?? $previousTable;
                    $targetRef = $targetAlias ?? $targetTable;

                    $joinConditionParts[] = sprintf(
                        '%s.%s = %s.%s',
                        $this->sanitizeSqlSimpleIdentifier($sourceRef),
                        $this->sanitizeSqlSimpleIdentifier($sourceCol),
                        $this->sanitizeSqlSimpleIdentifier($targetRef),
                        $this->sanitizeSqlSimpleIdentifier($targetCol)
                    );
                }

                $joinCondition = implode(' AND ', $joinConditionParts);

                // Apply the join.
                switch ($joinType) {
                    case 'LEFT':
                        $this->leftJoin($targetTable, $joinCondition, $targetAlias);
                        break;
                    case 'RIGHT':
                        $this->rightJoin($targetTable, $joinCondition, $targetAlias);
                        break;
                    case 'CROSS':
                        $this->crossJoin($targetTable, $targetAlias);
                        break;
                    case 'INNER':
                    default:
                        $this->innerJoin($targetTable, $joinCondition, $targetAlias);
                        break;
                }
            }

            $previousTable = $targetTable;
            $previousAlias = $targetAlias;
        }
    }
}
