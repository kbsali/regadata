<?php

namespace Service;

class Vg
{
    public $xlsDir, $docRoot, $jsonDir, $_report;

    public function __construct($xlsDir, $docRoot, $jsonDir, $_report)
    {
        $root = __DIR__ . '/../..';
        $this->xlsDir = $root . $xlsDir;
        $this->docRoot = $root . $docRoot;
        $this->jsonDir = $root . $jsonDir;
        $this->_report = $_report;
    }

    public function getSailCoordinates($id)
    {
        if (false === $arr = $this->parseJson('/sail/' . $id . '.json')) {
            return false;
        }

        return $this->extractSailsCoordinates($arr);
    }

    public function extractSailsCoordinates(array $arr = [])
    {
        $ret = [];
        foreach ($arr as $k => $v) {
            $ret[$k] = $v['lon_dec'] . ',' . $v['lat_dec'] . ',0';
        }

        return $ret;
    }

    public function getFullSailInfo($id)
    {
        if (false === $arr = $this->_report->findBy(null, ['sail' => $id], ['timestamp' => 1])) {
            return false;
        }

        return [
            'info' => end($arr),
            'rank' => $this->filterBy($arr, 'rank', 1),
            'dtl' => $this->filterBy($arr, 'dtl', 1),
            't24hour_distance' => $this->filterBy($arr, '24hour_distance', 1),
            't24hour_speed' => $this->filterBy($arr, '24hour_speed', 1),
            'tdtl_diff' => $this->filterBy($arr, 'dtl_diff', 1),
        ];
    }

    public function getReportsById($reports)
    {
        $s = '|/json/reports/(\d{4})(\d{2})(\d{2})-(\d{2})(\d{2}).json|s';
        $ret = [];
        foreach ($reports as $file) {
            preg_match($s, $file, $m);
            list($bla, $y, $mo, $d, $h, $min) = $m;
            $id = $y . $mo . $d . '-' . $h . $min;
            $ts = strtotime($y . '-' . $mo . '-' . $d . ' ' . $h . ':' . $min . ' -1 hour UTC');
            $ret[$id] = $ts;
        }

        return $ret;
    }

    public function listJson($type = null)
    {
        if (null === $type) {
            $ret = str_replace($this->docRoot, '', $this->jsonDir . '/FULL.json');

            return [$ret];
        }
        if ('sail' === $type) {
            $ret = [];
            foreach (glob($this->jsonDir . '/sail/*json') as $file) {
                $ret[] = str_replace($this->docRoot, '', $file);
            }

            return $ret;
        }
        if ('reports' === $type) {
            $ret = [];
            $files = glob($this->jsonDir . '/reports/*json');
            rsort($files);
            foreach ($files as $file) {
                $ret[] = str_replace($this->docRoot, '', $file);
            }

            return $ret;
        }

        return false;
    }

    public function parseJson($name = null)
    {
        $f = $this->jsonDir . (null === $name ? '/FULL.json' : $name);
        if (!file_exists($f)) {
            return false;
        }
        $json = file_get_contents($f);

        return json_decode($json, true);
    }

    /**
     * @param array  $arr
     * @param string $idx
     * @param int    $limitFactor
     *
     * @return array
     */
    public function filterBy(array $arr = [], $idx = null, $limitFactor = 10)
    {
        $i = 0;
        $ret = [];
        foreach ($arr as $_arr) {
            if (isset($_arr['has_arrived'])) {
                break;
            }
            ++$i;
            if ($limitFactor === $i) {
                $i = 0;
                $ret[] = [
                    (int) $_arr['timestamp'] * 1000, // flot
                    (int) $_arr[$idx], // flot
                    // 'x' => (int) $ts*1000, // nvd3
                    // 'y' => (int) $_arr[$idx] // nvd3
                ];
            }
        }

        return $ret;
    }

    public static function extractJsonBy($idx = null, $limitFactor = 10)
    {
        if (null === $idx) {
            return false;
        }
        $json = file_get_contents($this->jsonDir . '/FULL.json');
        foreach (json_decode($json, true) as $key => $arr) {
            $i = 0;
            foreach ($arr as $ts => $_arr) {
                ++$i;
                if ($limitFactor === $i) {
                    $i = 0;
                    $values[] = [
                        'x' => (int) $ts * 1000,
                        'y' => (int) $_arr[$idx],
                    ];
                }
            }
            $ret[] = [
                'key' => $key,
                'values' => $values,
                // 'color' => '#ff7f0e'
            ];
        }

        return json_encode($ret);
    }
}
