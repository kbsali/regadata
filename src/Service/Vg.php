<?php

namespace Service;

class Vg
{
    public $xlsDir, $docRoot, $jsonDir;

    public function __construct($xlsDir, $docRoot, $jsonDir)
    {
        $root = __DIR__.'/../..';
        $this->xlsDir  = $root.$xlsDir;
        $this->docRoot = $root.$docRoot;
        $this->jsonDir = $root.$jsonDir;
    }

    public function getSailCoordinates($id)
    {
        if (false === $arr = $this->parseJson('/sail/'.$id.'.json')) {
            return false;
        }

        return $this->extractSailsCoordinates($arr);
    }

    public function extractSailsCoordinates(array $arr = array())
    {
        $ret = array();
        foreach ($arr as $k => $v) {
            $ret[$k] = $v['lon_dec'].','.$v['lat_dec'].',0';
        }

        return $ret;
    }

    public function getFullSailInfo($id)
    {
        if (false === $arr = $this->parseJson('/sail/'.$id.'.json')) {
            return false;
        }

        return array(
            'info'             => end($arr),
            'rank'             => $this->filterBy($arr, 'rank', 1),
            'dtl'              => $this->filterBy($arr, 'dtl', 1),
            't24hour_distance' => $this->filterBy($arr, '24hour_distance', 1),
            't24hour_speed'    => $this->filterBy($arr, '24hour_speed', 1),
            'tdtl_diff'        => $this->filterBy($arr, 'dtl_diff', 1),
        );
    }

    public function getSailSkipper($report)
    {
        $ret = array();
        foreach ($report as $sail => $info) {
            $ret[$sail] = $info['skipper'].' ['.$info['boat'].']';
        }

        return $ret;
    }

    public function getReportsById($reports)
    {
        $s = '|/json/reports/(\d{4})(\d{2})(\d{2})-(\d{2})(\d{2}).json|s';
        $ret = array();
        foreach ($reports as $file) {
            preg_match($s, $file, $m);
            list($bla, $y, $mo, $d, $h, $min) = $m;
            $id = $y.$mo.$d.'-'.$h.$min;
            $ts = strtotime($y.'-'.$mo.'-'.$d.' '.$h.':'.$min.' -1 hour UTC');
            $ret[$id] = $ts;
        }

        return $ret;
    }

    public function listJson($type = null)
    {
        if (null === $type) {
            $ret = str_replace($this->docRoot, '', $this->jsonDir.'/FULL.json');

            return array($ret);
        }
        if ('sail' === $type) {
            $ret = array();
            foreach (glob($this->jsonDir.'/sail/*json') as $file) {
                $ret[] = str_replace($this->docRoot, '', $file);
            }

            return $ret;
        }
        if ('reports' === $type) {
            $ret = array();
            $files =  glob($this->jsonDir.'/reports/*json');
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
        $f = $this->jsonDir.(null === $name ? '/FULL.json' : $name);
        if (!file_exists($f)) {
            return false;
        }
        $json = file_get_contents($f);

        return json_decode($json, true);
    }

    /**
     * @param  array   $arr
     * @param  string  $idx
     * @param  integer $limitFactor
     * @return array
     */
    public function filterBy(array $arr = array(), $idx = null, $limitFactor = 10)
    {
        $i = 0;
        $ret = array();
        foreach ($arr as $ts => $_arr) {
            $i++;
            if ($limitFactor === $i) {
                $i = 0;
                $ret[] = array(
                    (int) $ts*1000, // flot
                    (int) $_arr[$idx], // flot
                    // 'x' => (int) $ts*1000, // nvd3
                    // 'y' => (int) $_arr[$idx] // nvd3
                );
            }
        }

        return $ret;
    }

    public static function extractJsonBy($idx = null, $limitFactor = 10)
    {
        if (null === $idx) {
            return false;
        }
        $json = file_get_contents($this->jsonDir.'/FULL.json');
        foreach (json_decode($json, true) as $key => $arr) {
            $i=0;
            foreach ($arr as $ts => $_arr) {
                $i++;
                if ($limitFactor === $i) {
                    $i = 0;
                    $values[] = array(
                        'x' => (int) $ts*1000,
                        'y' => (int) $_arr[$idx]
                    );
                }
            }
            $ret[] = array(
                'key'    => $key,
                'values' => $values,
                // 'color' => '#ff7f0e'
            );
        }

        return json_encode($ret);
    }

    public static function extractMaxByKey($report, $key)
    {
        $max = null;
        foreach ($report as $r) {
            if(null === $max || $r[$key] > $max[$key]) {
                $max = $r;
            }
        }
        return $max;
    }

    public function sailToTwitter($s)
    {
        $arr = array(
            // 'SUI9'
            // 'POL2'
            // 'FRA35'
            // 'FRA25'
            "FRA19"   => '@VoileBanquePop',
            'FRA44'   => '@Team_Plastique',
            'GBR99'   => '@AlexThomson99',
            'FRA14'   => '@AKENAVerandas60',
            'SUI2012' => '@Poujoulat_Stamm',
            'FRA62'   => '@BertranddeBroc',
            'FRA301'  => '@francoisgabart', // '@Macif60',
            'ESP4'    => '@AccionaSailing',
            'FRA59'   => '@LeCam_SynerCiel',
            'FRA06'   => '@Dick_JeanPierre',
            'FRA001'  => '@JeremieBeyou',
            'FRA360'  => '@GroupeBel60',
            'GBR3'    => '@Mike_Golding',
            'FRA29'   => '@samanthadavies',
            'FRA72'   => '@TanguyDeLamotte',
            'FRA85'   => '@Vincent_Riou',
        );
        if(!isset($arr[$s])) {
            return $s;
        }
        return $arr[$s];
    }

    public static function sailToColor($s)
    {
        $arr = array(
            "FRA19"   => '9ba5bd', // '061e5a',
            'FRA44'   => '558a42',
            'GBR99'   => '06040f',
            'FRA14'   => '12cf10',
            'SUI2012' => 'fac200',
            'FRA62'   => 'bf9c7c',
            'SUI9'    => '013983',
            'FRA301'  => '0b2e82',
            'ESP4'    => 'd01f27',
            'FRA59'   => '9ea1a2', // '0d1517',
            'FRA06'   => '3399c1',
            'FRA001'  => 'f10200',
            'FRA360'  => 'a71e2e',
            'FRA35'   => '1e9436',
            'FRA25'   => 'eb6c39',
            'GBR3'    => 'a5ad00',
            'FRA29'   => '65ac36',
            'FRA72'   => 'cc0107',
            'FRA85'   => 'fe640a',
            'POL2'    => 'c86347',
        );
        if(!isset($arr[$s])) {
            return $s;
        }
        return $arr[$s];
    }
}
