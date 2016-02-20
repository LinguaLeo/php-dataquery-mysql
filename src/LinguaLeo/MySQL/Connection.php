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

use LinguaLeo\MySQL\Interfaces\ScalableConnectionInterface;
use LinguaLeo\MySQL\Exception\MySQLException;
use LinguaLeo\MySQL\Model\ServerType;
use \PDO;

class Connection extends PDO implements ScalableConnectionInterface
{
    private $nestedTransactionCount = 0;
    private $serverType;

    public function __construct($host, $user, $passwd, $serverType = ServerType::MASTER, array $options = [])
    {
        $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES 'UTF8'";
        $options[PDO::ATTR_ERRMODE] = PDO::ERRMODE_EXCEPTION;

        $this->serverType = $serverType;

        parent::__construct('mysql:host='.$host, $user, $passwd, $options);
    }

    public function getServerType()
    {
        return $this->serverType;
    }

    public function ping()
    {
        return $this->query('SELECT 1');
    }

    public function beginTransaction()
    {
        $ok = true;
        if (0 === $this->nestedTransactionCount) {
            $ok = parent::beginTransaction();
        }
        $this->nestedTransactionCount++;
        return $ok;
    }

    public function commit()
    {
        $ok = true;
        if (1 === $this->nestedTransactionCount) {
            $ok = parent::commit();
        } elseif ($this->nestedTransactionCount < 1) {
            throw new MySQLException('You cannot make commit without begin', 10254);
        }
        $this->nestedTransactionCount--;
        return $ok;
    }

    public function rollBack(\Exception $e = null)
    {
        $ok = true;
        if (1 === $this->nestedTransactionCount) {
            $ok = parent::rollBack();
        } else {
            throw new MySQLException('Nested transaction is rolled back', 10255, $e);
        }
        $this->nestedTransactionCount--;
        return $ok;
    }
}
