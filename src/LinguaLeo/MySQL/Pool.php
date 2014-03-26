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

class Pool
{
    private $config;
    private $pool;

    public function __construct(Configuration $config)
    {
        $this->config = $config;
    }

    /**
     * Connects to a database.
     *
     * @param string $dbname
     * @param bool $force
     * @return Connection
     */
    public function connect($dbname, $force = false)
    {
        $host = $this->config->getHost($dbname);

        if (empty($this->pool[$host]) || $force) {
            $this->pool[$host] = $this->create($host);
        }

        return $this->pool[$host];
    }

    /**
     * Creates a connection.
     *
     * @param string $host
     * @return \LinguaLeo\MySQL\Connection
     */
    protected function create($host)
    {
        return new Connection($host, $this->config->getUser(), $this->config->getPasswd());
    }
}
