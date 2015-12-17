<?php
namespace LinguaLeo\MySQL\Interfaces;

use LinguaLeo\DataQuery\Criteria;
use LinguaLeo\MySQL\Connection;

interface DbConnectableInterface
{
    /**
     * Returns actual DB connection
     *
     * @param Criteria $criteria
     * @return Connection
     */
    public function getConnection(Criteria $criteria);
}
