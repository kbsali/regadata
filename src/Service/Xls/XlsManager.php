<?php

namespace Service\Xls;

abstract class XlsManager
{
    public $xlsDir, $jsonDir, $geoJsonDir, $kmlDir, $_report, $_sails, $_misc, $race;

    public function __construct($xlsDir, $jsonDir, $geoJsonDir, $kmlDir, $_report, $_misc, $race, $_sails)
    {
        $root = __DIR__.'/../../..';
        $this->_report = $_report;
        $this->_sails  = $_sails;
        $this->_misc   = $_misc;
        $this->race    = $race;

        $this->xlsDir = $root.$xlsDir.'/'.$this->race['id'];
        if (!is_dir($this->xlsDir)) {
            mkdir($this->xlsDir);
        }
        $this->jsonDir = $root.$jsonDir.'/'.$this->race['id'];
        if (!is_dir($this->jsonDir.'/reports')) {
            if (!mkdir($this->jsonDir.'/reports', 0777, true)) {
                throw new \Exception('Could not create dir '.$this->jsonDir.'/reports');
            }
        }
        if (!is_dir($this->jsonDir.'/sail')) {
            if (!mkdir($this->jsonDir.'/sail', 0777, true)) {
                throw new \Exception('Could not create dir '.$this->jsonDir.'/sail');
            }
        }
        $this->geoJsonDir = $root.$geoJsonDir.'/'.$this->race['id'];
        if (!is_dir($this->geoJsonDir.'/sail')) {
            if (!mkdir($this->geoJsonDir.'/sail', 0777, true)) {
                throw new \Exception('Could not create dir '.$this->geoJsonDir.'/sail');
            }
        }
        $this->kmlDir = $root.$kmlDir.'/'.$this->race['id'];
        if (!is_dir($this->kmlDir.'/reports')) {
            if (!mkdir($this->kmlDir.'/reports', 0777, true)) {
                throw new \Exception('Could not create dir '.$this->kmlDir.'/reports');
            }
        }
        if (!is_dir($this->kmlDir.'/sail')) {
            if (!mkdir($this->kmlDir.'/sail', 0777, true)) {
                throw new \Exception('Could not create dir '.$this->kmlDir.'/sail');
            }
        }
    }

    public function listMissingXlsx() {}
    public function downloadXlsx() {}
    public function xls2mongo($file = null, $force = false) {}
    protected function _getArrivalDate($date) {}
    protected function _getDate($data) {}

    private function kmlDeparture()
    {
        return strtr($this->_folder, array(
            '%name%'    => 'Departure',
            '%content%' => strtr($this->_deparr, array(
                '%name%'        => $this->race['departure'],
                '%coordinates%' => $this->race['departure_lon'].','.$this->race['departure_lat'],
            ))
        ));
    }

    private function kmlArrival()
    {
        return strtr($this->_folder, array(
            '%name%'    => 'Arrival',
            '%content%' => strtr($this->_deparr, array(
                '%name%'        => $this->race['arrival'],
                '%coordinates%' => $this->race['arrival_lon'].','.$this->race['arrival_lat'],
            ))
        ));
    }

    public function mongo2json($force = false, $by = 'id')
    {
        $reportIds = $this->_report->getAllBy($by);
        ksort($reportIds);
        $master = $total = $yesterday = array();

        // foreach ($reportIds as $reportId) {
        foreach ($reportIds as $ts) {
            $reportId = date('Ymd-Hi', $ts);
            $f = $this->jsonDir.'/reports/'.$reportId.'.json';
            $reports = $this->_report->findBy(null, array('id' => $reportId));
            $daily = array();
            foreach ($reports as $r) {
                unset($r['_id']);
                $daily[$r['sail']] = $r;
                $master[$r['sail']][$r['timestamp']] = $r;
            }

            if (true === $force || !file_exists($f)) {
                echo ' saving data to '.$f.PHP_EOL;
                file_put_contents($f, json_encode($daily));
            }
        }
        $this->export2geojson($master);
        $this->export2json($master);
        $this->export2kml($master);
    }

    public function export2geojson(array $arr = array())
    {

        foreach ($arr as $sail => $partial) {
            ksort($partial);
            $latest = end($partial);
            $tmp = array();
            foreach ($partial as $ts => $wp) {
                $tmp[] = array(
                    $wp['lon_dec'],
                    $wp['lat_dec'],
                );
            }
            $geojson = array(
                'type' => 'FeatureCollection',
                'features' => array(
                    array(
                        'type' => 'Feature',
                        'geometry' => array(
                            'type' => 'LineString',
                            'coordinates' => $tmp
                        ),
                        'properties' => array(
                            'popupContent' => $latest['skipper'],
                            'style' => array(
                                'weight' => 2,
                                'color' => '#999',
                                'opacity' => 1,
                                'fillColor' => '#B0DE5C',
                                'fillOpacity' => 0.8
                            )
                        ),
                    ),
                    array(
                        'type' => 'Feature',
                        'geometry' => array(
                            'type' => 'Point',
                            'coordinates' => array(
                                $latest['lon_dec'],
                                $latest['lat_dec'],
                            )
                        ),
                        'properties' => array(
                            'popupContent' => $latest['skipper'],
                            'style' => array(
                                'weight' => 2,
                                'color' => '#999',
                                'opacity' => 1,
                                'fillColor' => '#B0DE5C',
                                'fillOpacity' => 0.8
                            )
                        ),
                    ),
                ),
            );
            echo ' saving '.$sail.' data to '.$this->geoJsonDir.'/sail/'.$sail.'.geojson'.PHP_EOL;
            file_put_contents($this->geoJsonDir.'/sail/'.$sail.'.geojson', json_encode($geojson));
        }
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
        if (empty($arr)) {
            return;
        }
        $kmlFull = $lineFull = $pointsFull = $lastPosFull = '';
        foreach ($arr as $sail => $partial) {
            $end = end($partial);
            $kmlPartial = $this->arr2kml($partial);

            // line + points
            echo ' saving '.$sail.' pos to '.$this->kmlDir.'/sail/'.$sail.'.kml'.PHP_EOL;
            file_put_contents(
                $this->kmlDir.'/sail/'.$sail.'.kml',
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
                        $this->kmlDeparture().
                        $this->kmlArrival()
                ))
            );
            // line only
            echo ' saving '.$sail.' pos to '.$this->kmlDir.'/sail/trace_'.$sail.'.kml'.PHP_EOL;
            file_put_contents(
                $this->kmlDir.'/sail/trace_'.$sail.'.kml',
                strtr($this->_kml, array(
                    '%name%'      => $kmlPartial['name'],
                    '%atom_link%' => $this->race['host'],
                    '%content%'   =>
                        $kmlPartial['last_pos'].
                        $kmlPartial['line'].
                        $this->kmlDeparture().
                        $this->kmlArrival()
                ))
            );
            // points only
            echo ' saving '.$sail.' pos to '.$this->kmlDir.'/sail/points_'.$sail.'.kml'.PHP_EOL;
            file_put_contents(
                $this->kmlDir.'/sail/points_'.$sail.'.kml',
                strtr($this->_kml, array(
                    '%name%'      => $kmlPartial['name'],
                    '%atom_link%' => $this->race['host'],
                    '%content%'   =>
                        join(PHP_EOL, $kmlPartial['points']).
                        $this->kmlDeparture().
                        $this->kmlArrival()
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
        echo ' saving FULL data to '.$this->kmlDir.'/FULL.kml'.PHP_EOL;
        file_put_contents($this->kmlDir.'/FULL.kml',
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
                    $this->kmlDeparture().
                    $this->kmlArrival()
            ))
        );
        // kml (all in one file - line only)
        echo ' saving FULL data to '.$this->kmlDir.'/trace_FULL.kml'.PHP_EOL;
        file_put_contents($this->kmlDir.'/trace_FULL.kml',
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
                    $this->kmlDeparture().
                    $this->kmlArrival()
            ))
        );
        // kml (all in one file - points only)
        echo ' saving FULL data to '.$this->kmlDir.'/points_FULL.kml'.PHP_EOL;
        file_put_contents($this->kmlDir.'/points_FULL.kml',
            strtr($this->_kml, array(
                '%name%'      => $kmlPartial['name'],
                '%atom_link%' => $this->race['host'],
                '%content%'   =>
                    $pointsFull.
                    $this->kmlDeparture().
                    $this->kmlArrival()
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
        foreach ($coordinates as $ts => $coordinate) {
            $i++;
            $points[] = strtr($this->_point, array(
                '%color%'       => $this->_misc->hexToKml( $this->_misc->getColor($info['sail']) ),
                '%icon%'        => $j === $i ? 'http://'.$this->race['host'].'/icons/boat_'.$info['1hour_heading'].'.png' : 'http://maps.google.com/mapfiles/kml/shapes/placemark_circle.png',
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

    public static function DECtoDMS($dec)
    {
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

    /**
     * @param  string $str 48 17.23' N
     * @return array
     */
    public static function strtoDMS($str)
    {
        if (empty($str)) {
            return array(
                'deg' => 0,
                'min' => 0,
                'sec' => 0,
                'dir' => 0,
            );
        }
        if (strpos($str, '°')) {
            $regex = "|(.*?)°(.*?)\.(.*?)'([A-Z]{1})$|s";
        } else {
            $regex = "|(.*?) (.*?)\.(.*?)' ([A-Z]{1})$|s";
        }
        if (false === preg_match($regex, $str, $matches)) {
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

    public $_deparr = '<Placemark>
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
