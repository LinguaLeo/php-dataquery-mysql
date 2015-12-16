<?php

/**
 * The MIT License (MIT)
 *
 * Copyright (c) 2014 LinguaLeo
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

namespace LinguaLeo\MySQL;

use LinguaLeo\DataQuery\Exception\QueryException;
use LinguaLeo\DataQuery\Criteria;
use LinguaLeo\DataQuery\QueryDbConnectableInterface;
use LinguaLeo\DataQuery\Exception\CriteriaException;
use LinguaLeo\MySQL\Model\ServerType;
use LinguaLeo\MySQL\Model\CriteriaMetaParameter;

class Query implements QueryDbConnectableInterface
{
    /**
     * @var Pool
     */
    private $pool;

    /**
     * @var Routing
     */
    private $routing;

    /**
     * @var array
     */
    private $arguments;

    /**
     * @var Route
     */
    private $route;

    /**
     * Instantiates the query.
     *
     * @param Pool $pool
     * @param Routing $routing
     */
    public function __construct(Pool $pool, Routing $routing)
    {
        $this->pool = $pool;
        $this->routing = $routing;
    }

    private function getPlaceholders($count, $placeholder = '?')
    {
        return implode(',', array_fill(0, $count, $placeholder));
    }

    private function getFrom(Criteria $criteria)
    {
        return $this->route = $this->routing->getRoute($criteria);
    }

    private function getOrder($orderBy)
    {
        static $typesMap = [SORT_ASC => 'ASC', SORT_DESC => 'DESC'];
        $keys = [];
        foreach ((array)$orderBy as $field => $sortType) {
            if (empty($typesMap[$sortType])) {
                throw new QueryException(sprintf('Unknown %s sort type', $sortType));
            }
            $keys[] = $field . ' ' . $typesMap[$sortType];
        }
        return implode(', ', $keys);
    }

    private function getWhere(Criteria $criteria)
    {
        $this->arguments = [];

        if (empty($criteria->conditions)) {
            return 1;
        }

        $placeholders = [];

        foreach ($criteria->conditions as list($column, $value, $comparison)) {
            switch ($comparison) {
                case Criteria::IS_NOT_NULL:
                case Criteria::IS_NULL:
                    $placeholders[] = $column . ' ' . $comparison;
                    break;
                case Criteria::IN:
                case Criteria::NOT_IN:
                    $placeholders[] = $column . ' ' . $comparison . '(' . $this->getPlaceholders(count((array)$value)) . ')';
                    $this->arguments = array_merge($this->arguments, (array)$value);
                    break;
                default:
                    if (!is_scalar($value)) {
                        throw new QueryException(
                            sprintf(
                                'The %s type of value is wrong for %s comparison',
                                gettype($value),
                                $comparison
                            )
                        );
                    }
                    $placeholders[] = $column . $comparison . '?';
                    $this->arguments[] = $value;
            }
        }

        return implode(' AND ', $placeholders);
    }

    private function getExpression(Criteria $criteria)
    {
        if ($criteria->fields) {
            $fields = $criteria->fields;
        }
        if ($criteria->aggregations) {
            foreach ($criteria->aggregations as list($func, $field, $alias)) {
                if (!$field) {
                    $field = '*';
                }
                $fields[] = strtoupper($func).'('.$field.')' . ($alias ? ' AS ' . $alias : '');
            }
        }
        if (empty($fields)) {
            return '*';
        }
        return implode(',', $fields);
    }

    /**
     * Generate VALUES part of INSERT query
     *
     * @param Criteria $criteria
     * @return string
     * @throws QueryException
     */
    private function getValuesPlaceholders(Criteria $criteria)
    {
        $this->arguments = [];
        $columnsCount = count($criteria->fields);
        $rowsCount = null;
        foreach ($criteria->values as $columnIndex => $column) {
            if (!is_array($column)) {
                $column = [$column];
            }
            foreach ($column as $rowIndex => $value) {
                $this->arguments[$columnIndex + $rowIndex * $columnsCount] = $value;
            }
            if (null === $rowsCount) {
                $rowsCount = $rowIndex + 1;
            } elseif ($rowsCount !== $rowIndex + 1) {
                throw new QueryException(sprintf('Wrong rows count in %d column for multi insert query', $columnIndex));
            }
        }
        return $this->getPlaceholders($rowsCount, '('.$this->getPlaceholders($columnsCount).')');
    }

    /**
     * {@inheritdoc}
     */
    public function select(Criteria $criteria)
    {
        $SQL = 'SELECT ' . $this->getExpression($criteria)
            . ' FROM ' . $this->getFrom($criteria)
            . ' WHERE ' . $this->getWhere($criteria);

        if ($criteria->aggregations && $criteria->fields) {
            $SQL .= ' GROUP BY '.implode(',', $criteria->fields);
        }

        if ($criteria->orderBy) {
            $SQL .= ' ORDER BY '. $this->getOrder($criteria->orderBy);
        }

        if ($criteria->limit) {
            $SQL .= ' LIMIT ' . (int)$criteria->limit;
            if ($criteria->offset) {
                $SQL .= ' OFFSET ' . (int)$criteria->offset;
            }
        }

        return $this->executeQuery($SQL, $this->arguments, $this->getServerType($criteria));
    }

    /**
     * @param array $columns
     * @param array $values
     *
     * @return string
     * @throws QueryException
     */
    private function getDuplicateUpdatedValues(array $columns, array $values)
    {
        $updates = [];
        foreach ($columns as $column => $function) {
            if (is_int($column)) {
                $column = $function;
                $function = 'value';
            }
            switch ($function) {
                case 'value';
                    $updates[] = $column . '=VALUES(' . $column . ')';
                    break;
                case 'inc':
                    $updates[] = $column . '=' . $column . '+(?)';
                    if (!isset($values[$column])) {
                        throw new QueryException(sprintf('Value for "%s" isn`t specified', $column));
                    }
                    $this->arguments[] = $values[$column];
                    break;
                default:
                    throw new QueryException('Unsupported function: ' . $function);
            }
        }
        return implode(',', $updates);
    }

    /**
     * {@inheritdoc}
     */
    public function insert(Criteria $criteria)
    {
        if (empty($criteria->fields)) {
            throw new QueryException('No fields for insert statement');
        }
        if (ServerType::MASTER !== $this->getServerType($criteria)) {
            throw new QueryException('Write-queries can be executed only on MASTER');
        }

        $SQL = 'INSERT INTO ' . $this->getFrom($criteria) .
            '(' . implode(',', $criteria->fields) . ') VALUES ' . $this->getValuesPlaceholders($criteria);

        if ($criteria->upsert) {
            $SQL .= ' ON DUPLICATE KEY UPDATE '
            . $this->getDuplicateUpdatedValues($criteria->upsert, array_combine($criteria->fields, $criteria->values));
        }

        return $this->executeQuery($SQL, $this->arguments);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(Criteria $criteria)
    {
        if (ServerType::MASTER !== $this->getServerType($criteria)) {
            throw new QueryException('Write-queries can be executed only on MASTER');
        }

        $SQL = 'DELETE FROM ' . $this->getFrom($criteria) . ' WHERE ' . $this->getWhere($criteria);
        return $this->executeQuery($SQL, $this->arguments);
    }

    /**
     * {@inheritdoc}
     */
    public function update(Criteria $criteria)
    {
        if (ServerType::MASTER !== $this->getServerType($criteria)) {
            throw new QueryException('Write-queries can be executed only on MASTER');
        }

        return $this->executeUpdate($criteria, function ($fields) {
            return implode('=?,', $fields) . '=?';
        });
    }

    /**
     * {@inheritdoc}
     */
    public function increment(Criteria $criteria)
    {
        if (ServerType::MASTER !== $this->getServerType($criteria)) {
            throw new QueryException('Write-queries can be executed only on MASTER');
        }

        return $this->executeUpdate($criteria, function ($fields) {
            $placeholders = [];

            foreach ($fields as $field) {
                $placeholders[] = $field . '=' . $field . '+(?)';
            }

            return implode(',', $placeholders);
        });
    }

    /**
     * Returns actual DB connection
     *
     * @param Criteria $criteria
     * @return \PDO
     */
    public function getConnection(Criteria $criteria)
    {
        return $this->executeCallback(
            function (Connection $connection) {
                $connection->ping();
                return $connection;
            },
            $this->routing->getRoute($criteria)->getDbName(),
            $this->getServerType($criteria)
        );
    }

    /**
     * Returns expected server type
     *
     * @param Criteria $criteria
     */
    protected function getServerType(Criteria $criteria)
    {
        try {
            $serverType = (
                $criteria->getMeta(CriteriaMetaParameter::CAN_BE_READ_FROM_SLAVE) ?
                ServerType::SLAVE :
                ServerType::MASTER
            );
        } catch (CriteriaException $e) {
            $serverType = ServerType::MASTER;
        }

        return $serverType;
    }

    private function executeUpdate(Criteria $criteria, callable $placeholdersGenerator)
    {
        if (!$criteria->fields) {
            throw new QueryException('No fields for update statement');
        }

        $SQL = 'UPDATE ' . $this->getFrom($criteria)
            . ' SET ' . call_user_func($placeholdersGenerator, $criteria->fields)
            . ' WHERE ' . $this->getWhere($criteria);

        return $this->executeQuery($SQL, array_merge($criteria->values, $this->arguments));
    }

    /**
     * Executes the query with parameters
     *
     * @param string $query
     * @param array $params
     * @param int $serverType
     * @return \LinguaLeo\DataQuery\ResultInterface
     */
    protected function executeQuery($query, $params = [], $serverType = ServerType::MASTER)
    {

        return $this->executeCallback(
            function (Connection $connection) use ($query, $params) {
                return $this->getResult($connection, $query, $params);
            },
            $this->route->getDbName(),
            $serverType
        );
    }

    /**
     * Executes code and prevents query exceptions where possible
     *
     * @param callable $callback
     * @param string $dbName
     * @param int $serverType
     */
    protected function executeCallback(callable $callback, $dbName, $serverType = ServerType::MASTER)
    {
        $force = false;
        do {
            try {
                $connection = $this->pool->connectDB($dbName, $serverType, $force);
                if ($connection->inTransaction()) {
                    $force = true;
                }

                return call_user_func($callback, $connection);
            } catch (\PDOException $e) {
                $force = $this->hidePdoException($e, $force);
            }
        } while (true);
    }

    /**
     * Prevent query exception
     *
     * @param \PDOException $e
     * @param boolean $force
     * @return boolean
     * @throws \PDOException
     */
    private function hidePdoException(\PDOException $e, $force)
    {
        list($generalError, $code) = $e->errorInfo;
        switch ($code) {
            case 2006: // MySQL server has gone away
            case 2013: // Lost connection to MySQL server during query
                if (!$force) {
                    return true;
                }
            default:
                throw $e;
        }
    }

    /**
     * Run the query
     *
     * @param Connection $conn
     * @param string $query
     * @param array $params
     * @return \LinguaLeo\DataQuery\ResultInterface
     */
    private function getResult($conn, $query, $params)
    {
        if ($params) {
            $stmt = $conn->prepare($query);
            $stmt->execute($params);
        } else {
            $stmt = $conn->query($query);
        }
        return new Result($stmt);
    }
}
