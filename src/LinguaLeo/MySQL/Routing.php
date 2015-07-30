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

use LinguaLeo\DataQuery\Criteria;
use LinguaLeo\MySQL\Exception\RoutingException;

class Routing
{
    private $primaryDbName;
    private $tablesMap;

    private static $convertMap = [
        'chunked' => ['table_name', 'chunk_id'],
        'spotted' => ['db', 'spot_id'],
        'localized' => ['db', 'locale'],
    ];

    public function __construct($primaryDbName, array $tablesMap)
    {
        $this->primaryDbName = $primaryDbName;
        $this->tablesMap = $tablesMap;
    }

    /**
     * Prepare a route for a table
     *
     * @param Criteria $criteria
     * @return Route
     * @throws RoutingException
     */
    public function getRoute(Criteria $criteria)
    {
        $entry = $this->getEntry($criteria->location);
        if (isset($entry['options'])) {
            foreach ((array)$entry['options'] as $type) {
                list($placeholder, $parameter) = $this->getConvertOptions($type);
                $entry[$placeholder] = $this->getLocation($entry[$placeholder], $criteria->getMeta($parameter));
            }
        }
        return new Route($entry['db'], $entry['table_name']);
    }

    /**
     * Find an entry by table name
     *
     * @param string $tableName
     * @return array
     */
    private function getEntry($tableName)
    {
        $default = ['db' => $this->primaryDbName, 'table_name' => $tableName];
        if (empty($this->tablesMap[$tableName])) {
            return $default;
        }
        return (array)$this->tablesMap[$tableName] + $default;
    }

    /**
     * Returns convert options by type
     *
     * @param string $type
     * @return array(string,string) placeholder & parameter
     * @throws RoutingException
     */
    private function getConvertOptions($type)
    {
        if (empty(self::$convertMap[$type])) {
            throw new RoutingException(sprintf('Unknown "%s" option type', $type));
        }
        return self::$convertMap[$type];
    }

    /**
     * Builds a location by modifier
     *
     * @param string $location
     * @param mixed $modifier
     * @return string
     */
    private function getLocation($location, $modifier)
    {
        return $location.'_'.$modifier;
    }
}
