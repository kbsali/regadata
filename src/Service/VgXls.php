<?php

namespace Service;

use Service\Vg;

class VgXls
{
    public $xlsDir, $jsonDir, $_report, $_sk, $arrLat, $arrLng, $arrival;

    public function __construct($xlsDir, $jsonDir, $_report, $_sk, $arrLat, $arrLon, $arrival)
    {
        $root = __DIR__.'/../..';
        $this->xlsDir  = $root.$xlsDir;
        $this->jsonDir = $root.$jsonDir;
        $this->_report = $_report;
        $this->_sk     = $_sk;
        $this->arrLat  = $arrLat;
        $this->arrLon  = $arrLon;
        $this->arrival = $arrival;
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

    private function kmlDeparture()
    {
        return strtr($this->_folder, array(
            '%name%'    => 'Departure / Arrival',
            '%content%' => strtr($this->_departure, array(
                '%name%'        => $this->arrival,
                '%coordinates%' => $this->arrLon.','.$this->arrLat,
            ))
        ));
    }

    public function xls2mongo($file = null, $force = false)
    {
        require(__DIR__.'/../Util/XLSXReader.php');

        $xlsxs = glob(null === $file ? $this->xlsDir.'/*' : $file);
        sort($xlsxs);
        $i = 0;
        $total = $yesterday = array();
        foreach ($xlsxs as $xlsx) {
            echo $xlsx.PHP_EOL;
            $i++;
            try {
                $_xlsx = new \XLSXReader($xlsx);
                $data  = $_xlsx->getSheetData('fr');
                $ts    = $this->_getDate($data);
                foreach ($data as $row) {
                    if (false === $r = $this->_cleanRow($row, $ts, $xlsx)) {
                        continue;
                    }
                    if(!isset($total[$r['sail']])) {
                        $total[$r['sail']] = 0;
                    }
                    $total[$r['sail']]     += $r['lastreport_distance'];
                    $r['total_distance']   = $total[$r['sail']];
                    $r['dtl_diff']         = isset($yesterday[$r['sail']]) ? $r['dtl'] - $yesterday[$r['sail']]['dtl'] : 0;
                    $r['color']            = $this->_sk[$r['sail']]['color'];
                    $yesterday[$r['sail']] = $r;
                    try {
                        $this->_report->insert($r, $force);
                    } catch (\MongoCursorException $e) {
                        echo $e->getMessage().PHP_EOL;
                    }
                }
            } catch (\Exception $e) {
                continue;
            }
        }
    }

    public function mongo2json($force = false)
    {
        $tss    = $this->_report->getAllBy('timestamp');
        $master = $total = $yesterday = array();

        foreach($tss as $ts) {
            $f = $this->jsonDir.'/reports/'.date('Ymd-Hi', $ts).'.json';

            $reports = $this->_report->findBy(null, array('timestamp' => $ts));

            $daily = array();
            foreach($reports as $r) {
                unset($r['_id']);
                $daily[$r['sail']]       = $r;
                $master[$r['sail']][$ts] = $r;
            }

            if(true === $force || !file_exists($f)) {
                echo ' saving data to '.$f.PHP_EOL;
                file_put_contents($f, json_encode($daily));
            }
        }
        $this->export2json($master);
        $this->export2kml($master);
    }

    public function export2json(array $arr = array())
    {
        foreach ($arr as $sail => $partial) {
            echo ' saving '.$sail.' data to '.$this->jsonDir.'/sail/'.$sail.'.json'.PHP_EOL;
            file_put_contents($this->jsonDir.'/sail/'.$sail.'.json', json_encode($partial));
        }
        echo ' saving FULL data to '.$this->jsonDir.'/FULL.json'.PHP_EOL;
        file_put_contents($this->jsonDir.'/FULL.json', json_encode($arr));
    }

    public function export2kml(array $arr = array())
    {
        if(empty($arr)) {
            return;
        }
        $kmlFull = $lineFull = $pointsFull = $lastPosFull = '';
        foreach ($arr as $sail => $partial) {
            $end = end($partial);
            $kmlPartial = $this->arr2kml($partial);

            // line + points
            echo ' saving '.$sail.' pos to '.$this->jsonDir.'/sail/'.$sail.'.kml'.PHP_EOL;
            file_put_contents(
                $this->jsonDir.'/sail/'.$sail.'.kml',
                strtr($this->_kml, array(
                    '%name%'    => $kmlPartial['name'],
                    '%content%' =>
                        strtr($this->_folder, array(
                            '%name%'    => 'Trace',
                            '%content%' => $kmlPartial['line']
                        )).
                        strtr($this->_folder, array(
                            '%name%'    => 'Positions',
                            '%content%' => join(PHP_EOL, $kmlPartial['points']),
                        )).
                        strtr($this->_folder, array(
                            '%name%'    => 'Last Position',
                            '%content%' => $kmlPartial['last_pos']
                        )).
                        // strtr($this->_camera, array(
                        //     '%lon%' => $end['lon_dec'],
                        //     '%lat%' => $end['lat_dec'],
                        //     '%alt%' => 2000000,
                        // )).
                        $this->kmlDeparture()
                ))
            );
            // line only
            echo ' saving '.$sail.' pos to '.$this->jsonDir.'/sail/trace_'.$sail.'.kml'.PHP_EOL;
            file_put_contents(
                $this->jsonDir.'/sail/trace_'.$sail.'.kml',
                strtr($this->_kml, array(
                    '%name%'    => $kmlPartial['name'],
                    '%content%' =>
                        $kmlPartial['last_pos'].
                        $kmlPartial['line'].
                        $this->kmlDeparture()
                ))
            );
            // points only
            echo ' saving '.$sail.' pos to '.$this->jsonDir.'/sail/points_'.$sail.'.kml'.PHP_EOL;
            file_put_contents(
                $this->jsonDir.'/sail/points_'.$sail.'.kml',
                strtr($this->_kml, array(
                    '%name%'    => $kmlPartial['name'],
                    '%content%' =>
                        join(PHP_EOL, $kmlPartial['points']).
                        $this->kmlDeparture()
                ))
            );
            $lineFull    .= $kmlPartial['line'];
            $lastPosFull .= $kmlPartial['last_pos'];
            $pointsFull  .= strtr($this->_folder, array(
                '%name%'    => $kmlPartial['name'],
                '%content%' => join(PHP_EOL, $kmlPartial['points']),
            ));
        }

        // kml (all in one file - line + points)
        echo ' saving FULL data to '.$this->jsonDir.'/FULL.kml'.PHP_EOL;
        file_put_contents($this->jsonDir.'/FULL.kml',
            strtr($this->_kml, array(
                '%name%'    => $kmlPartial['name'],
                '%content%' =>
                    strtr($this->_folder, array(
                        '%name%'    => 'Trace',
                        '%content%' => $lineFull
                    )).
                    strtr($this->_folder, array(
                        '%name%'    => 'Positions',
                        '%content%' => $pointsFull,
                    )).
                    strtr($this->_folder, array(
                        '%name%'    => 'Last Positions',
                        '%content%' => $lastPosFull,
                    )).
                    // strtr($this->_camera, array(
                    //     '%lon%' => $first['lon_dec'],
                    //     '%lat%' => $first['lat_dec'],
                    //     '%alt%' => 2000000,
                    // )).
                    $this->kmlDeparture()
            ))
        );
        // kml (all in one file - line only)
        echo ' saving FULL data to '.$this->jsonDir.'/trace_FULL.kml'.PHP_EOL;
        file_put_contents($this->jsonDir.'/trace_FULL.kml',
            strtr($this->_kml, array(
                '%name%'    => $kmlPartial['name'],
                '%content%' =>
                    strtr($this->_folder, array(
                        '%name%'    => 'Trace',
                        '%content%' => $lineFull
                    )).
                    strtr($this->_folder, array(
                        '%name%'    => 'Last Positions',
                        '%content%' => $lastPosFull,
                    )).
                    $this->kmlDeparture()
            ))
        );
        // kml (all in one file - points only)
        echo ' saving FULL data to '.$this->jsonDir.'/points_FULL.kml'.PHP_EOL;
        file_put_contents($this->jsonDir.'/points_FULL.kml',
            strtr($this->_kml, array(
                '%name%'    => $kmlPartial['name'],
                '%content%' =>
                    $pointsFull.
                    $this->kmlDeparture()
            ))
        );

    }

    public function arr2kml(array $arr = array())
    {
        $info = current($arr);
        $coordinates = $this->extractSailsCoordinates($arr);

        $line = strtr($this->_line, array(
            '%color%'       => self::hexToKml( $this->_sk[$info['sail']]['color'] ),
            '%name%'        => '#'.$info['rank'].' '.$info['skipper'].' ['.$info['boat'].'] - Source : http://vg2012.saliou.name',
            '%coordinates%' => join(PHP_EOL, $coordinates),
        ));

        $points = array();
        $i = 0;$j = count($coordinates);
        foreach($coordinates as $ts => $coordinate) {
            $i++;
            $points[] = strtr($this->_point, array(
                '%color%'       => self::hexToKml($this->_sk[$info['sail']]['color']),
                '%icon%'        => $j === $i ? 'http://vg2012.saliou.name/icons/boat_'.$info['1hour_heading'].'.png' : 'http://maps.google.com/mapfiles/kml/shapes/placemark_circle.png',
                '%heading%'     => $info['1hour_heading'],
                '%coordinates%' => $coordinate,
                '%name%'        => $j === $i ? '#'.$info['rank'].' '.$info['skipper'] : '',
                '%description%' => strtr($this->_table, array(
                    '%name%'            => '#'.$info['rank'].' '.$info['skipper'],
                    '%boat%'            => $info['boat'],
                    '%date%'            => date('Y-m-d H:i', $ts),
                    '%color%'           => '#'.$this->_sk[$info['sail']]['color'],
                    '%1hour_speed%'     => sprintf('%.1f', $info['1hour_speed']),
                    '%24hour_speed%'    => sprintf('%.1f', $info['24hour_speed']),
                    '%1hour_distance%'  => sprintf('%.1f', $info['1hour_distance']),
                    '%24hour_distance%' => sprintf('%.1f', $info['24hour_distance']),
                    '%1hour_vmg%'       => sprintf('%.1f', $info['1hour_vmg']),
                    '%24hour_vmg%'      => sprintf('%.1f', $info['24hour_vmg']),
                    '%1hour_heading%'   => $info['1hour_heading'],
                    '%dtf%'             => sprintf('%.1f', $info['dtf']),
                    '%dtl%'             => sprintf('%.1f', $info['dtl']),
                ))
            ));
        }

        return array(
            'name'     => $info['skipper'].' ['.$info['boat'].']',
            'line'     => $line,
            'last_pos' => end($points),
            'points'   => $points,
        );
    }

    private function _cleanRow($row, $ts, $file)
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
        OR [4] =>  Date d'arrivée  ( Date of arrival ) : 27/01/2013 14:18:40 UTC -  Temps de course  ( Race time ) : 78j 02h 16min 40s

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

        $ret = array(
            'rank'                => (int) $rank,
            'country'             => trim($coun),
            'sail'                => trim($sail),
            'skipper'             => str_replace('  ', ' ', trim($sailor)),
            'boat'                => trim($boat),
            'source'              => basename($file),

            'time'                => 0,
            'date'                => date('Y-m-d', $ts),
            'id'                  => date('Ymd-Hi', $ts),
            'timestamp'           => $ts,

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

        if(null !== $row[4]) {
            $_ts = $this->_getArrivalDate($row[4]);

            $ret['timestamp'] = $_ts;
            $ret['time'] = date('H:i', $_ts);
            $ret['date'] = date('Y-m-d', $_ts);
            $ret['lat_dms'] = self::DECtoDMS($this->arrLat);
            $ret['lon_dms'] = self::DECtoDMS($this->arrLon);
            $ret['lat_dec'] = $this->arrLat;
            $ret['lon_dec'] = $this->arrLon;

            $ret['has_arrived'] = true;

            return $ret;
        }

        if (false === preg_match("|(\d{2}):(\d{2})|", $row[5], $time)) {
            $time[0] = 0;
        }
        $ret['time'] = $time[0];
        // $ret['id']   = date('Ymd', $ts).'-'.$time[0];

        $ret['lat_dms'] = trim($row[6]);
        $ret['lon_dms'] = trim($row[7]);
        $ret['lat_dec'] = self::DMStoDEC(self::strtoDMS(trim($row[6])));
        $ret['lon_dec'] = self::DMStoDEC(self::strtoDMS(trim($row[7])));

        $ret['1hour_heading']  = (int) trim($row[8], '°');
        $ret['1hour_speed']    = (float) trim($row[9], ' kts');
        $ret['1hour_vmg']      = (float) trim($row[10], ' kts');
        $ret['1hour_distance'] = (float) trim($row[11], ' nm');

        $ret['lastreport_heading']  = (int) trim($row[12], '°');
        $ret['lastreport_speed']    = (float) trim($row[13], ' kts');
        $ret['lastreport_vmg']      = (float) trim($row[14], ' kts');
        $ret['lastreport_distance'] = (float) trim($row[15], ' nm');

        $ret['24hour_heading']  = (int) trim($row[16], '°');
        $ret['24hour_speed']    = (float) trim($row[17], ' kts');
        $ret['24hour_vmg']      = (float) trim($row[18], ' kts');
        $ret['24hour_distance'] = (float) trim($row[19], ' nm');

        $ret['dtf'] = (float) trim($row[20], ' nm');
        $ret['dtl'] = (float) trim($row[21], ' nm');

        return $ret;
    }

    private function _getArrivalDate($date)
    {
        $s = ".*? : (.*?)/(.*?)/(.*?) (.*?) UTC .*?";
        preg_match('|'.$s.'|s', $date, $match);

        return strtotime($match[3].'-'.$match[2].'-'.$match[1].' '.$match[4].' UTC');
    }

    private function _getDate($data)
    {
        $date = $data[2][1];
        // Classement du 21/11/2012 à 04:00:00 UTC
        $s = 'Classement du (.*?)/(.*?)/(.*?) à (.*?) UTC';
        preg_match('|'.$s.'|s', $date, $match);

        return strtotime($match[3].'-'.$match[2].'-'.$match[1].' '.$match[4].' UTC');
    }

    /**
     * @param  string $str 26°53.06'W
     * @return array
     */
    public static function strtoDMS($str)
    {
        // preg_match("|(\d)°(\d{2}).(\d{2})'([A-Z]{1})$|s", $str, $matches);
        if (false === preg_match("|(.*?)°(.*?)\.(.*?)'([A-Z]{1})$|s", $str, $matches)) {
            return array(
                'deg' => 0,
                'min' => 0,
                'sec' => 0,
                'dir' => 0,
            );
        }

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
        if (isset($arr['dir']) && ('S' === $arr['dir'] || 'W' === $arr['dir'])) {
            return -$ret;
        }

        return $ret;
    }

    public static function DECtoDMS($dec) {
        $vars   = explode('.',$dec);
        $deg    = $vars[0];
        $tempma = '0.'.$vars[1];
        $tempma = $tempma * 3600;
        $min    = floor($tempma / 60);
        $sec    = $tempma - ($min*60);

        return $deg.'°'.$min.'.'.round($sec);
        return array(
            'deg' => $deg,
            'min' => $min,
            'sec' => $sec,
        );
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
    <atom:link href="http://vg2012.saliou.name" />
    %content%
</Document>
</kml>';

    public $_line = '<Placemark>
    <Style>
        <LineStyle>
            <color>%color%</color>
            <width>1</width>
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
    <name>%name%</name>
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
            <!-- <heading>%heading%</heading> -->
            <color>%color%</color>
        </IconStyle>
    </Style>
    <Point>
        <coordinates>%coordinates%</coordinates>
    </Point>
</Placemark>';

    public $_departure = '<Placemark>
    <name>%name%</name>
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

    public $_table = '
<table border="0" cellpadding="1" cellspacing="1">
    <tr>
        <td colspan="3">
            [%boat%]<br>
            %date%
            <hr color="%color%">
        </td>
    </tr>
    <tr>
        <td nowrap>Speed (kn) [1h / 24h]&nbsp;</td>
        <td align="right">%1hour_speed%&nbsp;&nbsp;</td>
        <td align="right">%24hour_speed%</td>
    </tr>
    <tr>
        <td nowrap>Distance (nm) [1h / 24h]&nbsp;</td>
        <td align="right">%1hour_distance%&nbsp;&nbsp;</td>
        <td align="right">%24hour_distance%</td>
    </tr>
    <tr>
        <td nowrap>VMG (kn) [1h / 24h]&nbsp;</td>
        <td align="right">%1hour_vmg%&nbsp;&nbsp;</td>
        <td align="right">%24hour_vmg%</td>
    </tr>
    <tr>
        <td nowrap>Heading (º)&nbsp;</td>
        <td colspan="2" align="right">%1hour_heading%</td>
    </tr>
    <tr>
        <td nowrap>DTF (nm)&nbsp;</td>
        <td colspan="2" align="right">%dtf%</td>
    </tr>
    <tr>
        <td nowrap>DTL (nm)&nbsp;</td>
        <td colspan="2" align="right">%dtl%</td>
    </tr>
</table>
<p>Source : http://vg2012.saliou.name</p>';
}