<?php
namespace LinguaLeo\MySQL\Interfaces;

interface DbConnectableInterface
{
    /**
     * Returns actual DB connection
     *
     * @param Criteria $criteria
     * @return \PDO
     */
    public function getConnection(Criteria $criteria);
}
