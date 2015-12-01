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

use LinguaLeo\MySQL\Model\ServerType;
use LinguaLeo\Cache\CacheInterface;
use LinguaLeo\Cache\TTL as CacheTTL;
use Psr\Log\LoggerInterface;

class Pool
{

    const MAX_FAILURES_DEFAULT = 15;
    const FAILURE_CACHE_KEY = 'PoolFailuresByHosts';

    /**
     * @var array
     */
    private $pool;

    /**
     * @var Configuration
     */
    protected $config;

    /**
     * @var CacheInterface
     */
    protected $cache;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var int
     */
    protected $maxFailures;

    /**
     * @param Configuration $config
     * @param CacheInterface $cache
     * @param LoggerInterface $logger
     * @param int $maxFailures
     */
    public function __construct(
        Configuration $config,
        CacheInterface $cache,
        LoggerInterface $logger,
        $maxFailures = self::MAX_FAILURES_DEFAULT
    ) {
        $this->config = $config;
        $this->cache = $cache;
        $this->logger = $logger;
        $this->maxFailures = $maxFailures;
    }

    /**
     * Connects to a database.
     *
     * @deprecated Use connectDB() instead
     *
     * @param string $dbname
     * @param bool $force
     * @return Connection
     */
    public function connect($dbname, $force = false)
    {
        $this->logger->warning('Pool: deprecated method used: connect()');
        return $this->connectDB($dbname, ServerType::MASTER, $force);
    }

    /**
     * Creates a connection.
     *
     * @deprecated Use connectHost() instead
     *
     * @param string $host
     * @return \LinguaLeo\MySQL\Connection
     */
    protected function create($host)
    {
        $this->logger->warning('Pool: deprecated method used: create()');
        return $this->connectHost($host, ServerType::MASTER);
    }

    /**
     * Connects to a database.
     *
     * @param string $dbname
     * @param int $serverType
     * @param bool $force
     * @return \LinguaLeo\MySQL\Connection
     */
    public function connectDB($dbname, $serverType = ServerType::MASTER, $force = false)
    {
        $host = $this->config->getHost($dbname);

        if (empty($this->pool[$host][$serverType]) || $force) {
            $this->pool[$host][$serverType] = $this->connectHost($host, $serverType);
        }

        return $this->pool[$host][$serverType];
    }

    /**
     * Creates a connection.
     *
     * @param string $host
     * @param int $serverType
     * @return \LinguaLeo\MySQL\Connection
     */
    protected function connectHost($host, $serverType)
    {
        $connectHost = $host;
        if ($serverType === ServerType::SLAVE) {
            if (!$connectHost = $this->getAvailableSlaveByMaster($host)) {
                $this->logger->notice(sprintf('Pool: cannot fetch slave available; host: %s', $host));
                return $this->connectHost($host, ServerType::MASTER);
            }
        }
        try {
            return new Connection($connectHost, $this->config->getUser(), $this->config->getPasswd());
        } catch (\PDOException $e) {
            if ($serverType === ServerType::SLAVE) {
                $this->incrementSlaveFailure($connectHost);
                $this->logger->critical(sprintf('Pool: slave failure detected; host: %s', $connectHost));
                return $this->connectHost($host, ServerType::MASTER);
            }
            throw $e;
        }
    }

    /**
     * @param string $masterHost
     * @return string|bool
     */
    protected function getAvailableSlaveByMaster($masterHost)
    {
        return $this->getRandomHostByList(
            $this->getAvailableSlavesFromList(
                $this->config->getSlaveHosts($masterHost)
            )
        );
    }

    /**
     * @param array $slaveHosts
     * @return array
     */
    protected function getAvailableSlavesFromList(array $slaveHosts)
    {
        return array_intersect(
            $slaveHosts,
            array_keys(
                array_filter(
                    $this->getSlaveFailureStatistics() + array_fill_keys($slaveHosts, 0),
                    function ($numFailures) {
                        return $numFailures <= $this->maxFailures;
                    }
                )
            )
        );
    }

    /**
     * @param array $hosts
     * @return string|bool
     */
    protected function getRandomHostByList(array $hosts)
    {
        return (empty($hosts) ? false : $hosts[array_rand($hosts)]);
    }

    /**
     * @return array
     */
    protected function getSlaveFailureStatistics()
    {
        return ($this->cache->get(self::FAILURE_CACHE_KEY) ?: []);
    }

    /**
     * @param string $host
     */
    protected function incrementSlaveFailure($host)
    {
        $statistics = $this->getSlaveFailureStatistics();
        return $this->updateSlaveFailure(
            $host,
            (isset($statistics[$host]) ? $statistics[$host]+1 : 1)
        );
    }

    /**
     * @param string $host
     * @param integer $value
     */
    protected function updateSlaveFailure($host, $value)
    {
        return $this->cache->set(
            self::FAILURE_CACHE_KEY,
            [$host => $value] + $this->getSlaveFailureStatistics(),
            CacheTTL::SHORT
        );
    }
}
