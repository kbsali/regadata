<?php

namespace Service\Xls;

class Vor2014Json extends XlsManager
{
    public function downloadAndParse($force = true)
    {
        $file = $this->xlsDir.'/VOLVO_WEB_LEG1_2014-'.date('Ymd-H00').'.json';
        if(!file_exists($file)) {
            file_put_contents($file, file_get_contents('http://www.volvooceanrace.com/en/rdc/VOLVO_WEB_LEG1_2014.json'));
        }
        $tmp = json_decode(file_get_contents($file), true);
        /*
        [tracksall] => Array
        (
            [code] => MAPF
            [reportdate] => 2014-10-11 22:06:09
            [timeoffix] => 2014-10-11 22:02:00
            [status] => RAC
            [latitude] => 36.95800
            [longitude] => -1.23667
            [dtf] => 06397.70
            [dtlc] => 00000
            [legstanding] => 3
            [twentyfourhourrun] => 0
            [legprogress] => 1
            [dul] => 00000.30
            [boatheadingtrue] => 233
            [smg] => 11.0
            [seatemperature] => 26
            [truwindspeedavg] => 14
            [speedthrowater] => 13
            [truewindspeedmax] => 16
            [truewinddirection] => 85
            [latestspeedthrowater] => 12
            [maxavgspeed] => 11.0
        )
         */
        /*
            [race_id] => vor2014-1
            [rank] => 0
            [country] =>
            [sail] =>
            [skipper] =>
            [boat] =>
            [source] =>
            [class] =>
            [id] =>
            [time] => 0
            [date] =>
            [timestamp] => 0
            [lat_dms] => 0
            [lon_dms] => 0
            [lat_dec] => 0
            [lon_dec] => 0
            [1hour_heading] => 0
            [1hour_speed] => 0
            [1hour_vmg] => 0
            [1hour_distance] => 0
            [lastreport_heading] => 0
            [lastreport_speed] => 0
            [lastreport_vmg] => 0
            [lastreport_distance] => 0
            [24hour_heading] => 0
            [24hour_speed] => 0
            [24hour_vmg] => 0
            [24hour_distance] => 0
            [dtf] => 0
            [dtl] => 0
        */
        foreach ($tmp['data']['tracksall'] as $track) {
            $r = array_combine($tmp['data']['fields'], $track);
            $ts = strtotime($r['reportdate'].' UTC');
            $row[$ts][(int) $r['legstanding']] = $this->_report->schema(
                [
                    'rank'      => $r['legstanding'],
                    'sail'      => $r['code'],
                    'skipper'   => $this->_misc->getSkipper($r['code']),
                    'boat'      => $this->_misc->getBoat($r['code']),
                    'color'     => $this->_misc->getColor($r['code']),
                    'source'    => basename($file),
                    'id'        => date('Ymd-Hi', $ts),
                    'date'      => date('Y-m-d', $ts),
                    'timestamp' => $ts,

                    'lat_dms' => self::DECtoDMS($r['latitude']),
                    'lon_dms' => self::DECtoDMS($r['longitude']),
                    'lat_dec' => $r['latitude'],
                    'lon_dec' => $r['longitude'],

                    'dtf'      => $r['dtf'],
                    'dtl_diff' => $r['dtlc'],
                    // 'dtl'      => 0,

                    'total_distance' => $this->race['total_distance'] - $r['dtf'],

                    '1hour_heading'       => $r['boatheadingtrue'],
                    '1hour_speed'         => $r['speedthrowater'],
                    '1hour_vmg'           => $r['smg'],
                    // '1hour_distance'      => 0,

                    // 'lastreport_heading'  => 0,
                    // 'lastreport_speed'    => 0,
                    // 'lastreport_vmg'      => 0,
                    // 'lastreport_distance' => 0,

                    // '24hour_heading'      => 0,
                    '24hour_speed'        => $r['twentyfourhourrun']/24,
                    // '24hour_vmg'          => 0,
                    '24hour_distance'     => $r['twentyfourhourrun'],
                ]
            );
        }
        // update dtl for each report
        foreach ($row as $ts => $reports) {
            foreach ($reports as $legstanding => $report) {
                if(1 !== $legstanding) {
                    $report['dtl'] = (float) $report['dtf'] - (float) $reports[1]['dtf'];
                }
                try {
                    // echo $report['id'].PHP_EOL;
                    $this->_report->insert($report, $force);
                } catch (\MongoCursorException $e) {
                    echo 'ERR'.PHP_EOL;
                    // echo $e->getMessage().PHP_EOL;
                }
            }
        }
    }
}
