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

    public function skipperToTwitter($s)
    {
        $arr = array(
            // 'Dominique Wavre'
            // 'Gutowski Zbigniew'
            // 'Louis Burton'
            // 'Marc Guillemot'
            "Armel Le Cléac'h"        => '@VoileBanquePop',
            'Alessandro Di Benedetto' => '@Team_Plastique',
            'Alex Thomson'            => '@AlexThomson99',
            'Arnaud Boissières'       => '@AKENAVerandas60',
            'Bernard Stamm'           => '@Poujoulat_Stamm',
            'Bertrand De Broc'        => '@BertranddeBroc',
            'François Gabart'         => '@francoisgabart', // '@Macif60',
            'Javier Sanso'            => '@AccionaSailing',
            'Jean Le Cam'             => '@LeCam_SynerCiel',
            'Jean-Pierre Dick'        => '@Dick_JeanPierre',
            'Jérémie Beyou'           => '@JeremieBeyou',
            'Kito De Pavant'          => '@GroupeBel60',
            'Mike Golding'            => '@Mike_Golding',
            'Samantha Davies'         => '@samanthadavies',
            'Tanguy Delamotte'        => '@TanguyDeLamotte',
            'Vincent Riou'            => '@Vincent_Riou',
        );
        if(!isset($arr[$s])) {
            return $s;
        }
        return $arr[$s];
    }

    public static function skipperToColor($s)
    {
        $arr = array(
            "Armel Le Cléac'h"        => 'FF0014', // 'ff0000ff',
            'Alessandro Di Benedetto' => '00B414',
            'Alex Thomson'            => 'BEBEBE',
            'Arnaud Boissières'       => 'F01478',
            'Bernard Stamm'           => '780A78',
            'Bertrand De Broc'        => '14F0F0',
            'Dominique Wavre'         => '0078FF',
            'François Gabart'         => 'F0C878',
            'Javier Sanso'            => 'FFF014',
            'Jean Le Cam'             => '00783C',
            'Jean-Pierre Dick'        => '14FAF0',
            'Jérémie Beyou'           => 'F07800',
            'Kito De Pavant'          => '1428B4',
            'Louis Burton'            => 'B478DC',
            'Marc Guillemot'          => '14A000',
            'Mike Golding'            => 'B4B414',
            'Samantha Davies'         => 'F01E78',
            'Tanguy Delamotte'        => '00786E',
            'Vincent Riou'            => 'C87814',
            'Gutowski Zbigniew'       => '007896',
        );
        if(!isset($arr[$s])) {
            return $s;
        }
        return $arr[$s];
    }
}
