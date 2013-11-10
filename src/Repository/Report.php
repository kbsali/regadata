<?php

namespace Repository;

class Report
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
        $this->col = $collectionName.'_reports';
        $this->_col = $this->_db->selectCollection($collectionName.'_reports');
        $this->_col->ensureIndex(array('race_id' => 1, 'sail' => 1, 'timestamp' => 1), array('unique' => true));
    }

    public function schema()
    {
        $ret = array(
            'race_id'             => $this->raceId,

            'rank'                => 0,
            'country'             => null,
            'sail'                => null,
            'skipper'             => null,
            'boat'                => null,
            'source'              => null,
            'class'               => null,

            'id'                  => null,
            'time'                => 0,
            'date'                => null,
            'timestamp'           => 0,

            'lat_dms'             => 0,
            'lon_dms'             => 0,
            'lat_dec'             => 0,
            'lon_dec'             => 0,

            '1hour_heading'       => 0,
            '1hour_speed'         => 0,
            '1hour_vmg'           => 0,
            '1hour_distance'      => 0,

            'lastreport_heading'  => 0,
            'lastreport_speed'    => 0,
            'lastreport_vmg'      => 0,
            'lastreport_distance' => 0,

            '24hour_heading'      => 0,
            '24hour_speed'        => 0,
            '24hour_vmg'          => 0,
            '24hour_distance'     => 0,

            'dtf'                 => 0,
            'dtl'                 => 0,
        );

        return $ret;
    }

    public function insert(array $r = array(), $force = false)
    {
        if (empty($r)) {
            return false;
        }
        if ($force) {
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
        foreach ($arrived as $a) {
            unset($a['_id']);
            $ret[$a['sail']] = $a;
        }

        return $ret;
    }

    public function getAllBy($key, $reverse = false)
    {
        $tmp = $this->_db
            ->command(array('distinct' => $this->col, 'key' => $key))
        ;
        if ($reverse) {
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
        if (null === $indexBy) {
            return iterator_to_array($tmp);
        }
        $ret = array();
        foreach (iterator_to_array($tmp) as $v) {
            $ret[$v[$indexBy]] = $v;
        }

        return $ret;
    }

    public function getLastTs()
    {
        $report = $this->_col
            ->find()
            ->sort(array('timestamp' => -1))
            ->limit(1)
        ;
        $tmp =current(iterator_to_array($report));

        return $tmp['timestamp'];
    }

    public function getLast()
    {
        return $this->findBy(null, array('timestamp' => $this->getLastTs()));
    }

    public function extractMaxByKey($report, $key)
    {
        $max = null;
        foreach ($report as $r) {
            if (null === $max || $r[$key] > $max[$key]) {
                $max = $r;
            }
        }

        return $max;
    }
}
