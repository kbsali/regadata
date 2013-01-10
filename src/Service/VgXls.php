<?php

namespace Service;

use Service\Vg;

class VgXls
{
    public $xlsDir, $jsonDir;

    public function __construct($xlsDir, $jsonDir)
    {
        $root = __DIR__.'/../..';
        $this->xlsDir  = $root.$xlsDir;
        $this->jsonDir = $root.$jsonDir;
    }

    public function listMissingXlsx()
    {
        $html = file_get_contents('http://www.vendeeglobe.org/fr/classement-historiques.html');
        $s = '<a href="http://tracking2012.vendeeglobe.org/download/(.*?)" title="Cliquer pour télécharger" target="_blank">';
        preg_match_all('|'.$s.'|s', $html, $matches);
        $ret = array();
        foreach ($matches[1] as $xlsx) {
            if (!file_exists($this->xlsDir.'/'.$xlsx)) {
                $ret[] = $xlsx;
            }
        }
        return $ret;
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
        require(__DIR__.'/../Util/XLSXReader.php');

        $master = $total = $yesterday = $first = array();
        $xlsxs = glob($this->xlsDir.'/*');
        sort($xlsxs);
        foreach ($xlsxs as $xlsx) {
            try {
                $xlsx = new \XLSXReader($xlsx);
            } catch (\Exception $e) {
                continue;
            }
            $data  = $xlsx->getSheetData('fr');
            $ts    = $this->_getDate($data);
            $daily = array();
            foreach ($data as $row) {
                if (false === $r = $this->_cleanRow($row, $ts)) {
                    continue;
                }
                if(!isset($total[$r['sail']])) {
                    $total[$r['sail']] = 0;
                }
                $total[$r['sail']]+= $r['lastreport_distance'];
                $r['total_distance']     = $total[$r['sail']];
                $r['dtl_diff']           = isset($yesterday[$r['sail']]) ? $r['dtl'] - $yesterday[$r['sail']]['dtl'] : 0;
                $r['color']              = Vg::sailToColor($r['sail']);
                $daily[$r['sail']]       = $r;
                $yesterday[$r['sail']]   = $r;
                $master[$r['sail']][$ts] = $r;
            }
            echo ' saving data to '.$this->jsonDir.'/reports/'.date('Ymd-Hi', $ts).'.json'.PHP_EOL;
            file_put_contents($this->jsonDir.'/reports/'.date('Ymd-Hi', $ts).'.json', json_encode($daily));
        }
        foreach ($daily as $r) {
            if(1 == $r['rank']) {
                $first['lat_dec'] = $r['lat_dec'];
                $first['lon_dec'] = $r['lon_dec'];
            }
        }

        // export to json
        foreach ($master as $sail => $partial) {
            echo ' saving '.$sail.' data to '.$this->jsonDir.'/sail/'.$sail.'.json'.PHP_EOL;
            file_put_contents($this->jsonDir.'/sail/'.$sail.'.json', json_encode($partial));
        }
        // export to kml
        $kmlFull = $lineFull = $pointsFull = '';
        foreach ($master as $sail => $partial) {
            $end = end($partial);
            $kmlPartial = $this->arr2kml($partial);

            // line + points
            echo ' saving '.$sail.' pos to '.$this->jsonDir.'/sail/'.$sail.'.kml'.PHP_EOL;
            file_put_contents(
                $this->jsonDir.'/sail/'.$sail.'.kml',
                strtr($this->_kml, array(
                    '%name%'    => $kmlPartial['name'],
                    '%content%' =>
                        $kmlPartial['line'].
                        strtr($this->_folder, array(
                            '%name%'    => 'Positions',
                            '%content%' => join(PHP_EOL, $kmlPartial['points']),
                        )).
                        strtr($this->_camera, array(
                            '%lon%' => $end['lon_dec'],
                            '%lat%' => $end['lat_dec'],
                            '%alt%' => 2000000,
                        ))
                ))
            );
            // line only
            echo ' saving '.$sail.' pos to '.$this->jsonDir.'/sail/trace_'.$sail.'.kml'.PHP_EOL;
            file_put_contents(
                $this->jsonDir.'/sail/trace_'.$sail.'.kml',
                strtr($this->_kml, array(
                    '%name%'    => $kmlPartial['name'],
                    '%content%' => $kmlPartial['line']
                ))
            );
            // points only
            echo ' saving '.$sail.' pos to '.$this->jsonDir.'/sail/points_'.$sail.'.kml'.PHP_EOL;
            file_put_contents(
                $this->jsonDir.'/sail/points_'.$sail.'.kml',
                strtr($this->_kml, array(
                    '%name%'    => $kmlPartial['name'],
                    '%content%' => join(PHP_EOL, $kmlPartial['points'])
                ))
            );
            $lineFull.= $kmlPartial['line'];
            $pointsFull.= strtr($this->_folder, array(
                '%name%'    => $kmlPartial['name'],
                '%content%' => join(PHP_EOL, $kmlPartial['points']),
            ));
        }
        echo ' saving FULL data to '.$this->jsonDir.'/FULL.json'.PHP_EOL;
        // json (all in one file)
        file_put_contents($this->jsonDir.'/FULL.json', json_encode($master));

        // kml (all in one file - line + points)
        echo ' saving FULL data to '.$this->jsonDir.'/FULL.kml'.PHP_EOL;
        file_put_contents($this->jsonDir.'/FULL.kml',
            strtr($this->_kml, array(
                '%name%'    => $kmlPartial['name'],
                '%content%' =>
                    $lineFull.
                    strtr($this->_folder, array(
                        '%name%'    => 'Positions',
                        '%content%' => $pointsFull,
                    )).
                    strtr($this->_camera, array(
                        '%lon%' => $first['lon_dec'],
                        '%lat%' => $first['lat_dec'],
                        '%alt%' => 2000000,
                    ))
            ))
        );
        // kml (all in one file - line only)
        echo ' saving FULL data to '.$this->jsonDir.'/trace_FULL.kml'.PHP_EOL;
        file_put_contents($this->jsonDir.'/trace_FULL.kml',
            strtr($this->_kml, array(
                '%name%'    => $kmlPartial['name'],
                '%content%' => $lineFull
            ))
        );
        // kml (all in one file - points only)
        echo ' saving FULL data to '.$this->jsonDir.'/points_FULL.kml'.PHP_EOL;
        file_put_contents($this->jsonDir.'/points_FULL.kml',
            strtr($this->_kml, array(
                '%name%'    => $kmlPartial['name'],
                '%content%' => $pointsFull
            ))
        );
    }

    public function arr2kml(array $arr = array())
    {
        $info = current($arr);
        $coordinates = $this->extractSailsCoordinates($arr);

        $line = strtr($this->_line, array(
            '%color%'       => self::hexToKml(Vg::sailToColor($info['sail'])),
            '%name%'        => '#'.$info['rank'].' '.$info['skipper'].' ['.$info['boat'].'] - Source : http://vg2012.saliou.name',
            '%coordinates%' => join(PHP_EOL, $coordinates),
        ));

        $points = array();
        $i = 0;$j = count($coordinates);
        foreach($coordinates as $ts => $coordinate) {
            $i++;
            $points[] = strtr($this->_point, array(
                '%color%'       => self::hexToKml(Vg::sailToColor($info['sail'])),
                '%icon%'        => $j ===  $i ? 'http://maps.google.com/mapfiles/kml/shapes/arrow.png' : 'http://maps.google.com/mapfiles/kml/shapes/placemark_circle.png',
                '%heading%'     => $info['1hour_heading']+180,
                '%coordinates%' => $coordinate,
                // '%name%'        => '#'.$info['rank'].' '.$info['skipper'].' ['.$info['boat'].'] - Source : http://vg2012.saliou.name',
                '%description%' => '<p>'.date('Y-m-d H:i', $ts).'
<br>#'.$info['rank'].' '.$info['skipper'].' ['.$info['boat'].']</p>
<table>
    <tr>
        <td>&nbsp;</td>
        <td><strong>Heading</strong></td>
        <td><strong>Speed</strong></td>
        <td><strong>VMG</strong></td>
        <td><strong>Distance</strong></td>
    </tr>
    <tr>
        <td><strong>1 hour</strong></td>
        <td>'.$info['1hour_heading'].'</td>
        <td>'.$info['1hour_speed'].'</td>
        <td>'.$info['1hour_vmg'].'</td>
        <td>'.$info['1hour_distance'].'</td>
    </tr>
    <tr>
        <td><strong>24 hours</strong></td>
        <td>'.$info['24hour_heading'].'</td>
        <td>'.$info['24hour_speed'].'</td>
        <td>'.$info['24hour_vmg'].'</td>
        <td>'.$info['24hour_distance'].'</td>
    </tr>
</table>
<p>Source : http://vg2012.saliou.name</p>',
            ));
        }

        return array(
            'name'   => $info['skipper'].' ['.$info['boat'].']',
            'line'   => $line,
            'points' => $points,
        );
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
            'skipper'             => str_replace('  ', ' ', trim($sailor)),
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
        // preg_match("|(\d)°(\d{2}).(\d{2})'([A-Z]{1})$|s", $str, $matches);
        preg_match("|(.*?)°(.*?)\.(.*?)'([A-Z]{1})$|s", $str, $matches);

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


    public function extractSailsCoordinates(array $arr = array())
    {
        $ret = array();
        foreach ($arr as $k => $v) {
            $ret[$k] = $v['lon_dec'].','.$v['lat_dec'].',0';
        }

        return $ret;
    }

    public static function hexToKml($color, $aa = 'ff')
    {
        $rr = substr($color, 0, 2);
        $gg = substr($color, 2, 2);
        $bb = substr($color, 4, 2);
        return strtolower($aa.$bb.$gg.$rr);
    }

    public static function hexToRgb($color)
    {
        $rgb = array();
        for ($x=0;$x<3;$x++) {
            $rgb[$x] = hexdec(substr($color, (2*$x), 2));
        }
        return $rgb;
    }

    public static function kmlToRgb($color)
    {
        $rr = substr($color, 6, 2);
        $gg = substr($color, 4, 2);
        $bb = substr($color, 2, 2);
        return strtolower($rr.$gg.$bb);
    }


    public $_kml = '<?xml version="1.0" encoding="utf-8" ?>
<kml xmlns="http://www.opengis.net/kml/2.2"
     xmlns:atom="http://www.w3.org/2005/Atom">
<Document>
    <atom:author>
        <atom:name>Kevin Saliou</atom:name>
    </atom:author>
    <atom:link href="http://vg2012.saliou.name" />
    %content%
</Document>
</kml>';

    public $_line = '<Placemark>
    <Style>
        <LineStyle>
            <color>%color%</color>
        </LineStyle>
        <PolyStyle>
            <fill>0</fill>
        </PolyStyle>
    </Style>
    <name>%name%</name>
    <LineString>
        <coordinates>
            %coordinates%
        </coordinates>
    </LineString>
</Placemark>';

    public $_point = '<Placemark>
    <description>
    <![CDATA[
        %description%
    ]]>
    </description>
    <Style>
        <IconStyle>
            <Icon>
                <href>%icon%</href>
            </Icon>
            <heading>%heading%</heading>
            <color>%color%</color>
        </IconStyle>
    </Style>
    <Point>
        <coordinates>%coordinates%</coordinates>
    </Point>
</Placemark>';

    public $_folder = '<Folder>
    <name>%name%</name>
    %content%
</Folder>';

    public $_camera = '<Camera>
    <longitude>%lon%</longitude>
    <latitude>%lat%</latitude>
    <altitude>%alt%</altitude>
    <altitudeMode>relativeToSeaFloor</altitudeMode>
</Camera>';
}