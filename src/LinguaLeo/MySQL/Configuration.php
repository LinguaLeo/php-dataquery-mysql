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

use LinguaLeo\MySQL\Exception\PoolException;

class Configuration
{
    protected $map;
    protected $user;
    protected $passwd;

    /**
     * Instantiate the configuration
     *
     * @param array $map The databases mapping on hosts
     * @param string $user
     * @param string $passwd
     */
    public function __construct(array $map, $user, $passwd)
    {
        $this->map = $map;
        $this->user = $user;
        $this->passwd = $passwd;
    }

    public function getUser()
    {
        return $this->user;
    }

    public function getPasswd()
    {
        return $this->passwd;
    }

    /**
     * Returns the host for the database
     *
     * @param string $dbname
     * @return string
     * @throws PoolException
     */
    public function getHost($dbname)
    {
        if (empty($this->map[$dbname])) {
            throw new PoolException(sprintf('Host is not defined for %s database', $dbname));
        }

        return $this->map[$dbname];
    }
}
