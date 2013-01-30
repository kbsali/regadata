<?php

namespace Repository;

class Sail
{
    private $mongo, $_sail;

    public function __construct($mongo)
    {
        $this->mongo = $mongo;

        $this->_sail = $this->mongo->regatta->sails;
        $this->_sail->ensureIndex(array('sail' => 1), array('unique' => true));
    }

    public function insert(array $r = array(), $force = false)
    {
        if(empty($r)) {
            return false;
        }
        if($force) {
            return $this->_sail->update(array('sail' => $r['sail']), $r, array('safe' => true));
        }
        return $this->_sail->insert($r, array('safe' => true));
    }

    public function getAllBy($key, $reverse = false)
    {
        $tmp = $this->mongo->regatta
            ->command(array('distinct' => 'sails', 'key' => $key))
        ;
        if($reverse) {
            rsort($tmp['values']);
        }
        return $tmp['values'];
    }

    public function findBy(array $arr = array())
    {
        return $this->_sail
            ->find($arr)
            ->sort(array('skipper' => 1))
        ;
    }
}