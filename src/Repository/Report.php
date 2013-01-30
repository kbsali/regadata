<?php

namespace Repository;

class Report
{
    private $mongo, $_report;

    public function __construct($mongo)
    {
        $this->mongo = $mongo;

        $this->_report = $this->mongo->regatta->reports;
        $this->_report->ensureIndex(array('sail' => 1, 'timestamp' => 1), array('unique' => true));
    }

    public function insert(array $r = array(), $force = false)
    {
        if(empty($r)) {
            return false;
        }
        if($force) {
            return $this->_report->update(array('timestamp' => $r['timestamp'], 'sail' => $r['sail']), $r, array('safe' => true));
        }
        return $this->_report->insert($r, array('safe' => true));
    }

    public function getHasArrived()
    {
        $arrived = $this->_report
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
        $tmp = $this->mongo->regatta
            ->command(array('distinct' => 'reports', 'key' => $key))
        ;
        if($reverse) {
            rsort($tmp['values']);
        }
        return $tmp['values'];
    }

    public function findBy(array $arr = array())
    {
        return $this->_report
            ->find($arr)
            ->sort(array('rank' => 1))
        ;
    }
}