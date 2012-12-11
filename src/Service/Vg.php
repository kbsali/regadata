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

    public function getFullSailInfo($id)
    {
        $arr = $this->parseJson('/sail/'.$id.'.json');
        return array(
            'info'             => end($arr),
            'rank'             => $this->filterBy($arr, 'rank', 1),
            'dtl'              => $this->filterBy($arr, 'dtl', 1),
            't24hour_distance' => $this->filterBy($arr, '24hour_distance', 1),
            't24hour_speed'    => $this->filterBy($arr, '24hour_speed', 1),
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
            $ts = strtotime($y.'-'.$mo.'-'.$d.' '.$h.':'.$min.' UTC');
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
        $json = file_get_contents($this->jsonDir.(null === $name ? '/FULL.json' : $name));

        return json_decode($json, true);
    }

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

    public function downloadXlsx()
    {
        $html = file_get_contents('http://www.vendeeglobe.org/fr/classement-historiques.html');
        $s = '<a href="http://tracking2012.vendeeglobe.org/download/(.*?)" title="Cliquer pour télécharger" target="_blank">';
        preg_match_all('|'.$s.'|s', $html, $matches);
        foreach ($matches[1] as $xlsx) {
            echo 'checking '.$xlsx;
            if (!file_exists($this->xlsDir.'/'.$xlsx)) {
                echo ' - downloading from http://tracking2012.vendeeglobe.org/download/'.$xlsx;
                file_put_contents($this->xlsDir.'/'.$xlsx, file_get_contents('http://tracking2012.vendeeglobe.org/download/'.$xlsx));
            }
            echo PHP_EOL;
        }
    }

    public function xls2json()
    {
        require(__DIR__.'/../vendor/XLSXReader/XLSXReader.php');

        $master = array();
        $files = glob($this->xlsDir.'/*');
        foreach ($files as $file) {
            try {
                $xlsx = new XLSXReader($file);
            } catch (Exception $e) {
                continue;
            }
            $data  = $xlsx->getSheetData('fr');
            $ts    = $this->_getDate($data);
            $daily = array();
            foreach ($data as $row) {
                if (false === $r = $this->_cleanRow($row, $ts)) {
                    continue;
                }
                $daily[$r['sail']]       = $r;
                $master[$r['sail']][$ts] = $r;

            }
            echo ' saving data to '.$this->jsonDir.'/reports/'.date('Ymd-Hi', $ts).'.json'.PHP_EOL;
            file_put_contents($this->jsonDir.'/reports/'.date('Ymd-Hi', $ts).'.json', json_encode($daily));
        }
        foreach ($master as $sail => $partial) {
            echo ' saving '.$sail.' data to '.$this->jsonDir.'/sail/'.$sail.'.json'.PHP_EOL;
            file_put_contents($this->jsonDir.'/sail/'.$sail.'.json', json_encode($partial));
        }
        echo ' saving FULL data to '.$this->jsonDir.'/FULL.json'.PHP_EOL;
        file_put_contents($this->jsonDir.'/FULL.json', json_encode($master));
    }

    private function _getDate($data)
    {
        $date = $data[2][1];
        // Classement du 21/11/2012 à 04:00:00 UTC
        $s = 'Classement du (.*?)/(.*?)/(.*?) à (.*?) UTC';
        preg_match('|'.$s.'|s', $date, $match);

        return strtotime($match[3].'-'.$match[2].'-'.$match[1].' '.$match[4].' UTC');
    }

    private function _cleanRow($row, $ts)
    {
        $rank = trim(trim($row[1]), ' ');
        if ('RET' == $rank) {
            return false;
            /*
            list($coun, $sail) = explode(PHP_EOL, trim($row[2]));
            list($sailor, $boat) = explode(PHP_EOL, trim($row[3]));
            $ret = array(
                'rank'    => (int) $rank,
                'country' => trim($coun),
                'sail'    => trim($sail),
                'sailor'  => trim($sailor),
                'boat'    => trim($boat),
                'status'  => 'RETIRED',
            );

            return $ret;
            */
        } else {
            if (0 === (int) $rank ) {
                return false;
            }
        }
        /* --- IN ---
        [0]  =>
        [1]  =>  14
        [2]  =>  IT
        FRA44
        [3]  =>  Alessandro Di Benedetto
        Team Plastique
        [4]  =>
        [5]  =>  03:00

        [6]  => 18°25.83'N
        [7]  => 26°53.06'W
        [8]  => 213°
        [9]  => 10.9 kts
        [10] => 8.6 kts
        [11] => 10.9 nm
        [12] => 196°
        [13] => 10.9 kts
        [14] => 10.3 kts
        [15] => 98.5 nm
        [16] => 200°
        [17] => 10.8 kts
        [18] => 10.1 kts
        [19] => 260.3 nm
        [20] => 22045.5 nm
        [21] => 949.2 nm
        [22] =>
         */
        /* --- OUT ---
        [rank]                => 1
        [country]             => FR
        [sail]                => FRA19
        [sailor]              => Armel Le Cléac'h
        [boat]                => Banque Populaire
        [time]                => 03:00
        [lat]                 => 00°51.12'N
        [lon]                 => 28°19.67'W
        [1hour_heading]       => 183
        [1hour_speed]         => 11.1
        [1hour_vmg]           => 6.2
        [1hour_distance]      => 11.1
        [lastreport_heading]  => 184
        [lastreport_speed]    => 10.6
        [lastreport_vmg]      => 5.8
        [lastreport_distance] => 95.5
        [24hour_heading]      => 190
        [24hour_speed]        => 9.6
        [24hour_vmg]          => 4.6
        [24hour_distance]     => 230.9
        [dtf]                 => 21096.3
        [dtl]                 => 0
        */
        list($coun, $sail)   = explode(PHP_EOL, trim($row[2]));
        list($sailor, $boat) = explode(PHP_EOL, trim($row[3]));
        preg_match("|(\d{2}):(\d{2})|", $row[5], $time);

        $ret = array(
            'rank'                => (int) $rank,
            'country'             => trim($coun),
            'sail'                => trim($sail),
            'skipper'             => trim($sailor),
            'boat'                => trim($boat),

            'time'                => $time[0],
            'date'                => date('Y-m-d', $ts),
            'timestamp'           => $ts,

            'lat_dms'             => trim($row[6]),
            'lon_dms'             => trim($row[7]),
            'lat_dec'             => self::DMStoDEC(self::strtoDMS(trim($row[6]))),
            'lon_dec'             => self::DMStoDEC(self::strtoDMS(trim($row[7]))),

            '1hour_heading'       => (int) trim($row[8], '°'),
            '1hour_speed'         => (float) trim($row[9], ' kts'),
            '1hour_vmg'           => (float) trim($row[10], ' kts'),
            '1hour_distance'      => (float) trim($row[11], ' nm'),

            'lastreport_heading'  => (int) trim($row[12], '°'),
            'lastreport_speed'    => (float) trim($row[13], ' kts'),
            'lastreport_vmg'      => (float) trim($row[14], ' kts'),
            'lastreport_distance' => (float) trim($row[15], ' nm'),

            '24hour_heading'      => (int) trim($row[16], '°'),
            '24hour_speed'        => (float) trim($row[17], ' kts'),
            '24hour_vmg'          => (float) trim($row[18], ' kts'),
            '24hour_distance'     => (float) trim($row[19], ' nm'),

            'dtf'                 => (float) trim($row[20], ' nm'),
            'dtl'                 => (float) trim($row[21], ' nm'),
        );

        return $ret;
    }

    /**
     * @param  string $str 26°53.06'W
     * @return array
     */
    public static function strtoDMS($str)
    {
        preg_match("|(\d{2})°(\d{2}).(\d{2})'([A-Z]{1})$|s", $str, $matches);

        return array(
            'deg' => $matches[1],
            'min' => $matches[2],
            'sec' => $matches[3],
            'dir' => $matches[4],
        );

        return $matches;
    }

    /**
     * @param  array $arr Array([deg] => 18, [min] => 25, [sec] => 83, [dir] => N)
     * @return float
     */
    public static function DMStoDEC(array $arr = array())
    {
        $ret = $arr['deg'] + ( ( ($arr['min']*60) + $arr['sec'] ) / 3600 );
        if ('S' === $arr['dir'] || 'W' === $arr['dir']) {
            return -$ret;
        }

        return $ret;
    }
}
