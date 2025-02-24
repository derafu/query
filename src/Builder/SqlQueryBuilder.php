<?php

declare(strict_types=1);

/**
 * Derafu: Query - Smart Query Builder.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Query\Builder;

use Derafu\Query\Builder\Contract\QueryBuilderInterface;
use Derafu\Query\Builder\Sql\SqlBuilderWhere;
use Derafu\Query\Builder\Sql\SqlSanitizeIdentifierTrait;
use Derafu\Query\Engine\Contract\SqlEngineInterface;
use Derafu\Query\Filter\CompositeCondition;
use Derafu\Query\Filter\Contract\CompositeConditionInterface;
use Derafu\Query\Filter\Contract\ConditionInterface;
use Derafu\Query\Filter\Contract\ExpressionParserInterface;
use RuntimeException;

/**
 * SQL implementation of the query builder.
 *
 * This builder generates SQL queries. It translates string filter expressions
 * into SQL using the expression parsers.
 */
final class SqlQueryBuilder implements QueryBuilderInterface
{
    use SqlSanitizeIdentifierTrait;

    /**
     * The columns to select.
     *
     * @var array<string>
     */
    private array $columns = ['*'];

    /**
     * The base table name.
     *
     * @var string
     */
    private string $table;

    /**
     * The table alias if any.
     *
     * @var string
     */
    private string $alias;

    /**
     * The where conditions (always as a composite condition).
     *
     * @var CompositeConditionInterface
     */
    private CompositeConditionInterface $where;

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
    public function select(string|array|null $columns = null, bool $sanitize = true): self
    {
        if (is_string($columns)) {
            $this->columns = array_map('trim', explode(',', $columns));
        } elseif (is_array($columns)) {
            $this->columns = $columns;
        }

        if ($sanitize) {
            $this->columns = array_map(
                [$this, 'sanitizeIdentifier'],
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
        $this->table = $table;

        if ($alias !== null) {
            $this->alias = $alias;
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
    public function getQuery(): array
    {
        if (!isset($this->table)) {
            throw new RuntimeException('No table specified for query.');
        }

        $sql = 'SELECT ' . implode(', ', $this->columns);
        $sql .= ' FROM ' . $this->table;
        if (isset($this->alias)) {
            $sql .= ' AS ' . $this->alias;
        }

        $parameters = [];

        if (isset($this->where)) {
            $conditionBuilder = new SqlBuilderWhere($this->engine->getDriver());
            $result = $conditionBuilder->build($this->where)->getQuery();
            $sql .= ' WHERE ' . $result['sql'];
            $parameters = $result['parameters'];
        }

        return [
            'sql' => $sql,
            'parameters' => $parameters,
        ];
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
}
