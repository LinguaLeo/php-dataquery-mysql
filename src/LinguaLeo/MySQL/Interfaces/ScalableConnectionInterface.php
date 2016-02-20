<?php
namespace LinguaLeo\MySQL\Interfaces;

interface ScalableConnectionInterface
{
    /**
     * @return int
     */
    public function getServerType();
}
