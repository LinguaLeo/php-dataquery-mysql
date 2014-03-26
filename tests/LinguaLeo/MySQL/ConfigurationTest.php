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

class ConfigurationTest extends \PHPUnit_Framework_TestCase
{
    private $configuration;

    public function setUp()
    {
        parent::setUp();

        $this->configuration = new Configuration(['db_name' => 'hostname'], 'root', 'Pa$$w0rd');
    }

    public function testGetUser()
    {
        $this->assertSame('root', $this->configuration->getUser());
    }

    public function testGetPassword()
    {
        $this->assertSame('Pa$$w0rd', $this->configuration->getPasswd());
    }

    public function testGetHost()
    {
        $this->assertSame('hostname', $this->configuration->getHost('db_name'));
    }

    /**
     * @expectedException \LinguaLeo\MySQL\Exception\PoolException
     * @expectedExceptionMessage Host is not defined for ololo database
     */
    public function testGetUndefinedHost()
    {
        $this->configuration->getHost('ololo');
    }
}