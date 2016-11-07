<?php

namespace Repository;

class Sail
{
    private $mongo, $_db, $_col, $raceId;

    public function __construct($mongo, $race = null)
    {
        $this->mongo = $mongo;
        $this->_db = $this->mongo->regatta;
        $this->_initDb($race);
    }

    public function setRace($race)
    {
        $this->_initDb($race);
    }

    private function _initDb($collectionName = null)
    {
        if (null === $collectionName) {
            return false;
        }
        $this->raceId = $collectionName;
        $this->col = $collectionName . '_sails';
        $this->_col = $this->_db->selectCollection($collectionName . '_sails');
        $this->_col->createIndex(['sail' => 1], ['unique' => true]);
    }

    public function insert(array $r = [], $force = false)
    {
        if (empty($r)) {
            return false;
        }
        if ($force) {
            return $this->_col->update(['sail' => $r['sail']], $r, ['safe' => true]);
        }

        return $this->_col->insertOne($r, ['safe' => true]);
    }

    public function getAllBy($key, $reverse = false)
    {
        $tmp = $this->_db
            ->command(['distinct' => $this->col, 'key' => $key])
        ;
        if ($reverse) {
            rsort($tmp['values']);
        }

        return $tmp['values'];
    }

    public function findBy($indexBy = null, array $arr = [], array $orderby = ['skipper' => 1])
    {
        $tmp = $this->_col
            ->find($arr, $orderby)
        ;
        if (null === $indexBy) {
            return iterator_to_array($tmp);
        }
        $ret = [];
        foreach (iterator_to_array($tmp) as $v) {
            $ret[$v[$indexBy]] = $v;
        }

        return $ret;
    }
}
