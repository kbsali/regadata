<?php

namespace Service;

use Service\Vg;

class TbmXls
{
    public $xlsDir, $jsonDir, $_report, $_misc, $race, $ts;

    public function __construct($xlsDir, $jsonDir, $_report, $_misc, $race, $_sails)
    {
        $root          = __DIR__.'/../..';
        $this->_report = $_report;
        $this->_sails  = $_sails;
        $this->_misc   = $_misc;
        $this->race    = $race;

        $this->xlsDir  = $root.$xlsDir.'/'.$this->race['id'];
        if(!is_dir($this->xlsDir)) {
            mkdir($this->xlsDir);
        }
        $this->jsonDir  = $root.$jsonDir.'/'.$this->race['id'];
        if(!is_dir($this->jsonDir)) {
            mkdir($this->jsonDir);
        }
    }

    public function listMissingXlsx()
    {
        $html = file_get_contents('http://www.transat-bretagnemartinique.com/fr/s10_classement/s10p02_all_class.php');
        $s = '<a href="../s10_classement/s10p04_get_xls.php\?no_classement=(.*?)" target="_blank">';
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
        $html = file_get_contents('http://www.transat-bretagnemartinique.com/fr/s10_classement/s10p02_all_class.php');
        $s = '<a href="../s10_classement/s10p04_get_xls.php?no_classement=(.*?)" target="_blank">';
        preg_match_all('|'.$s.'|s', $html, $matches);
        foreach ($matches[1] as $xlsx) {
            echo 'checking '.$xlsx;
            if (!file_exists($this->xlsDir.'/'.$xlsx)) {
                $url = 'http://www.transat-bretagnemartinique.com/fr/s10_classement/s10p04_get_xls.php?no_classement='.$xlsx;
                echo ' - downloading from '.$url;
                file_put_contents($this->xlsDir.'/'.$xlsx, file_get_contents($url));
            }
            echo PHP_EOL;
        }
    }

    private function kmlDeparture()
    {
        return strtr($this->_folder, array(
            '%name%'    => 'Departure / Arrival',
            '%content%' => strtr($this->_departure, array(
                '%name%'        => $this->race['arrival'],
                '%coordinates%' => $this->race['arrLon'].','.$this->race['arrLat'],
            ))
        ));
    }

    public function xls2mongo($file = null, $force = false)
    {
        require(__DIR__.'/../Util/Spreadsheet_Excel_Reader.php');
        require(__DIR__.'/../Util/SpreadsheetReader_XLS.php');

        $this->boats = $this->_sails->findBy('boat2');

        $xlsxs = glob(null === $file ? $this->xlsDir.'/*' : $file);
        sort($xlsxs);
        $i = 0;
        $total = $yesterday = array();
        foreach ($xlsxs as $xlsx) {
            echo $xlsx.PHP_EOL;
            $i++;
            try {
                $data = new \SpreadsheetReader_XLS($xlsx);
                foreach ($data as $row) {
                    $this->_getDate($row);
                    if(null === $this->ts) {
                        continue;
                    }
                    if (false === $r = $this->_cleanRow($row, $this->ts, $xlsx)) {
                        continue;
                    }
                    // ld($r);continue;

                    if(!isset($total[$r['sail']])) {
                        $total[$r['sail']] = 0;
                    }
                    $total[$r['sail']]     += $r['lastreport_distance'];
                    $r['total_distance']   = $total[$r['sail']];
                    $r['dtl_diff']         = isset($yesterday[$r['sail']]) ? $r['dtl'] - $yesterday[$r['sail']]['dtl'] : 0;
                    $r['color']            = $this->_misc->getColor($r['sail']);
                    $yesterday[$r['sail']] = $r;
                    try {
                        // print_R($this->_report->insert($r, $force));
                        $this->_report->insert($r, $force);
                    } catch (\MongoCursorException $e) {
                        // echo $e->getMessage().PHP_EOL;
                    }
                }
            } catch (\Exception $e) {
                throw new \Exception('WHAT? '.$e->getMessage());

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
                    '%name%'      => $kmlPartial['name'],
                    '%atom_link%' => $this->race['host'],
                    '%content%'   =>
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
                    '%name%'      => $kmlPartial['name'],
                    '%atom_link%' => $this->race['host'],
                    '%content%'   =>
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
                    '%name%'      => $kmlPartial['name'],
                    '%atom_link%' => $this->race['host'],
                    '%content%'   =>
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
                '%name%'      => $kmlPartial['name'],
                '%atom_link%' => $this->race['host'],
                '%content%'   =>
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
                '%name%'      => $kmlPartial['name'],
                '%atom_link%' => $this->race['host'],
                '%content%'   =>
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
                '%name%'      => $kmlPartial['name'],
                '%atom_link%' => $this->race['host'],
                '%content%'   =>
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
            '%color%'       => $this->_misc->hexToKml( $this->_misc->getColor($info['sail']) ),
            '%name%'        => '#'.$info['rank'].' '.$info['skipper'].' ['.$info['boat'].'] - Source : '.$this->race['host'],
            '%coordinates%' => join(PHP_EOL, $coordinates),
        ));

        $points = array();
        $i = 0;$j = count($coordinates);
        foreach($coordinates as $ts => $coordinate) {
            $i++;
            $points[] = strtr($this->_point, array(
                '%color%'       => $this->_misc->hexToKml( $this->_misc->getColor($info['sail']) ),
                '%icon%'        => $j === $i ? $this->race['host'].'/icons/boat_'.$info['1hour_heading'].'.png' : 'http://maps.google.com/mapfiles/kml/shapes/placemark_circle.png',
                '%heading%'     => $info['1hour_heading'],
                '%coordinates%' => $coordinate,
                '%name%'        => $j === $i ? '#'.$info['rank'].' '.$info['skipper'] : '',
                '%description%' => strtr($this->_table, array(
                    '%name%'            => '#'.$info['rank'].' '.$info['skipper'],
                    '%source_link%'     => $this->race['host'],
                    '%boat%'            => $info['boat'],
                    '%date%'            => date('Y-m-d H:i', $ts),
                    '%color%'           => '#'.$this->_misc->getColor($info['sail']),
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
        $rank = trim(trim($row[0]), ' ');
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
        [0] =>  1
        [1] => GROUPE QUEGUINER - LEUCEMIE ESPOIR
        [2] => Yann Elies
        [3] => 3453.44
        [4] =>
        [5] => 17/03/2013 15:45:00
        [6] => 48 17.23' N
        [7] => 4 41.21' W
        [8] => 4.8
        [9] => 4.5
        [10] => 270
        [11] =>
        [12] =>
        [13] =>
        [14] =>
        [15] =>
         */
        $ret = $this->_report->schema();
        $ret['rank']      = (int) $rank;
        $ret['country']   = trim('fr');
        $ret['skipper']   = utf8_decode(trim($row[2]));
        // ld($row);
        // ld($ret);
        // ldd($this->boats);
        // // $ret['sail']      = $this->boats[ $ret['skipper'] ];
        $boat      = trim($row[1]);
        // $ret['boat']      = trim($row[1]);
        $ret['sail']      = !isset($this->boats[ $boat ]) ? null : $this->boats[ $boat ]['sail'];
        $ret['skipper']      = !isset($this->boats[ $boat ]) ? null : $this->boats[ $boat ]['skipper'];
        $ret['boat']      = !isset($this->boats[ $boat ]) ? null : $this->boats[ $boat ]['boat'];
        // $ret['boat']      = !isset($this->boats[ $ret['boat'] ]) ? null : $this->boats[ $ret['boat'] ]['sail'];
        $ret['source']    = basename($file);

        $ret['id']        = date('Ymd-Hi', $ts);
        $ret['date']      = date('Y-m-d', $ts);
        $ret['timestamp'] = $ts;

        // ----------------------------
        // HANDLE ARRIVAL (WHAT IS THE FORMAT???)
        // if('' !== trim($row[4])) {
        //     $_ts = $this->_getArrivalDate($row[4]);

        //     $ret['timestamp'] = $_ts;
        //     $ret['time'] = date('H:i', $_ts);
        //     $ret['date'] = date('Y-m-d', $_ts);
        //     $ret['lat_dms'] = self::DECtoDMS($this->race['arrival_lat']);
        //     $ret['lon_dms'] = self::DECtoDMS($this->race['arrival_lon']);
        //     $ret['lat_dec'] = $this->race['arrival_lat'];
        //     $ret['lon_dec'] = $this->race['arrival_lat'];

        //     $ret['has_arrived'] = true;

        //     return $ret;
        // }
        // ----------------------------

        if (false === preg_match("|(\d{2}):(\d{2})|", $row[5], $time)) {
            $time[0] = 0;
        }
        $ret['time'] = $time[0];

        $ret['lat_dms'] = trim($row[6]);
        $ret['lon_dms'] = trim($row[7]);
        $ret['lat_dec'] = self::DMStoDEC(self::strtoDMS(trim($row[6])));
        $ret['lon_dec'] = self::DMStoDEC(self::strtoDMS(trim($row[7])));

        $ret['1hour_speed']    = (float) trim($row[8]);
        $ret['1hour_vmg']      = (float) trim($row[9]);
        $ret['1hour_heading']  = (int) trim($row[10]);
        // $ret['1hour_distance'] = (float) trim($row[11]);

        $ret['lastreport_vmg']      = (float) trim($row[11]);
        $ret['lastreport_heading']  = (int) trim($row[12]);
        // $ret['lastreport_speed']    = (float) trim($row[11]);
        // $ret['lastreport_distance'] = (float) trim($row[15]);

        $ret['24hour_vmg']  = (int) trim($row[13]);
        $ret['24hour_distance'] = (float) trim($row[14]);

        $ret['dtf'] = (float) trim($row[3]);
        $ret['dtl'] = (float) trim($row[4]);

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
        if(false === strpos($data[0], 'Date retenue pour')) {
            return false;
        }
        // Figaro - Date retenue pour le calcul du classement intermiaire estim: 17/03/13 15:45 Fr
        $s = ': (.*?)/(.*?)/(.*?) (.*?) Fr';
        preg_match('|'.$s.'|s', $data[0], $match);

        $this->ts = strtotime($match[3].'-'.$match[2].'-'.$match[1].' '.$match[4].' UTC');
    }

    /**
     * @param  string $str 48 17.23' N
     * @return array
     */
    public static function strtoDMS($str)
    {
        // preg_match("|(\d)°(\d{2}).(\d{2})'([A-Z]{1})$|s", $str, $matches);
        if (false === preg_match("|(.*?) (.*?)\.(.*?)' ([A-Z]{1})$|s", $str, $matches)) {
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

    public $_kml = '<?xml version="1.0" encoding="utf-8" ?>
<kml xmlns="http://www.opengis.net/kml/2.2"
     xmlns:atom="http://www.w3.org/2005/Atom">
<Document>
    <atom:link href="http://%atom_link%" />
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
<p>Source : http://%source_link%</p>';
}