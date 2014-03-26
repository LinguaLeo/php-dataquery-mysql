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

use LinguaLeo\DataQuery\ResultInterface;

class Result implements ResultInterface
{
    /**
     * @var \PDOStatement
     */
    private $stmt;

    public function __construct(\PDOStatement $stmt)
    {
        $this->stmt = $stmt;
    }

    public function __destruct()
    {
        $this->free();
    }

    /**
     * {@inheritdoc}
     */
    public function keyValue()
    {
        $result = [];
        while ($row = $this->stmt->fetch(\PDO::FETCH_NUM)) {
            $result[$row[0]] = $row[1];
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function many()
    {
        return $this->stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * {@inheritdoc}
     */
    public function one()
    {
        return $this->stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * {@inheritdoc}
     */
    public function table()
    {
        $table = [];
        while ($row = $this->stmt->fetch(\PDO::FETCH_ASSOC)) {
            foreach ($row as $column => $value) {
                $table[$column][] = $value;
            }
        }
        return $table;
    }

    /**
     * {@inheritdoc}
     */
    public function value($name)
    {
        $row = $this->one();
        if (isset($row[$name])) {
            return $row[$name];
        }
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function column($number)
    {
        return $this->stmt->fetchColumn($number);
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        return $this->stmt->rowCount();
    }

    /**
     * {@inheritdoc}
     */
    public function free()
    {
        if ($this->stmt) {
            $this->stmt->closeCursor();
            $this->stmt = null;
            return true;
        }
        return false;
    }
}