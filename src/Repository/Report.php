<?php

namespace Repository;

class Report
{
    private $mongo, $_db, $_col;

    public function __construct($mongo)
    {
        $this->mongo = $mongo;

        $this->_db = $this->mongo->regatta;
        $this->_col = $this->_db->reports;
        $this->_col->ensureIndex(array('sail' => 1, 'timestamp' => 1), array('unique' => true));
    }

    public function insert(array $r = array(), $force = false)
    {
        if(empty($r)) {
            return false;
        }
        if($force) {
            return $this->_col->update(
                array(
                    'timestamp' => $r['timestamp'],
                    'sail'      => $r['sail']
                ),
                $r,
                array(
                    'safe' => true
                )
            );
        }
        return $this->_col->insert($r, array('safe' => true));
    }

    public function getHasArrived()
    {
        $arrived = $this->_col
            ->find(array('has_arrived' => true))
        ;
        $ret = array();
        foreach($arrived as $a) {
            unset($a['_id']);
            $ret[$a['sail']] = $a;
        }
        return $ret;
    }

    public function getAllBy($key, $reverse = false)
    {
        $tmp = $this->_db
            ->command(array(
                'distinct' => 'reports',
                'key'      => $key
            ))
        ;
        if($reverse) {
            rsort($tmp['values']);
        }
        return $tmp['values'];
    }

    public function findBy($indexBy = null, array $arr = array(), array $orderby = array('rank' => 1))
    {
        $tmp = $this->_col
            ->find($arr)
            ->sort($orderby)
        ;
        if(null === $indexBy) {
            return iterator_to_array($tmp);
        }
        $ret = array();
        foreach (iterator_to_array($tmp) as $v) {
            $ret[$v[$indexBy]] = $v;
        }
        return $ret;
    }
}