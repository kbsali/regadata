<?php

namespace Service\Xls;

class Vg2016Xls extends XlsManager implements XlsManagerInterface
{
    private $urlHistory = 'http://www.vendeeglobe.org/fr/historique-des-classements';
    private $baseDlUrl = 'http://www.vendeeglobe.org/download-race-data/%s';

    public function listMissingXlsx()
    {
        $html = file_get_contents($this->urlHistory);
        $s = '<a href="/download-race-data/(.*?)" class="item__link" download>';
        preg_match_all('|' . $s . '|s', $html, $matches);
        $ret = [];
        foreach ($matches[1] as $xlsx) {
            if (!file_exists($this->xlsDir . '/' . $xlsx)) {
                $ret[] = $xlsx;
            }
        }

        return $ret;
    }

    public function downloadXlsx()
    {
        $html = file_get_contents($this->urlHistory);
        $s = '<a href="/download-race-data/(.*?)" class="item__link" download>';
        preg_match_all('|' . $s . '|s', $html, $matches);
        foreach ($matches[1] as $xlsx) {
            echo 'checking ' . $xlsx;
            if (!file_exists($this->xlsDir . '/' . $xlsx)) {
                echo ' - downloading from http://www.vendeeglobe.org/download-race-data/' . $xlsx;
                file_put_contents($this->xlsDir . '/' . $xlsx, file_get_contents(sprintf($this->baseDlUrl, $xlsx)));
            }
            echo PHP_EOL;
        }
    }

    public function xls2mongo($file = null, $force = false)
    {
        require __DIR__ . '/../../Util/XLSXReader.php';

        $xlsxs = glob(null === $file ? $this->xlsDir . '/*' : $file);
        sort($xlsxs);
        $i = 0;
        $total = $yesterday = [];
        foreach ($xlsxs as $xlsx) {
            echo $xlsx . PHP_EOL;
            ++$i;
            try {
                $_xlsx = new \XLSXReader($xlsx);
                $data = $_xlsx->getSheetData('fr');
                $ts = $this->_getDate($data);
                foreach ($data as $row) {
                    if (false === $r = $this->_cleanRow($row, $ts, $xlsx)) {
                        continue;
                    }
                    if (!isset($total[$r['sail']])) {
                        $total[$r['sail']] = 0;
                    }
                    $total[$r['sail']] += $r['lastreport_distance'];
                    $r['total_distance'] = $total[$r['sail']];
                    $r['dtl_diff'] = isset($yesterday[$r['sail']]) ? $r['dtl'] - $yesterday[$r['sail']]['dtl'] : 0;
                    $r['color'] = $this->_misc->getColor($r['sail']);
                    $yesterday[$r['sail']] = $r;
                    try {
                        // print_R($this->_report->insert($r, $force));
                        $this->_report->insert($r, $force);
                    } catch (\MongoCursorException $e) {
                        // echo $e->getMessage().PHP_EOL;
                    }
                }
            } catch (\Exception $e) {
                continue;
            }
        }
    }

    private function _cleanRow($row, $ts, $file)
    {
        $rank = trim(trim($row[1]), ' ');
        if ('RET' === $rank) {
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
            if (0 === (int) $rank) {
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
        list($coun, $sail) = explode(PHP_EOL, trim($row[2]));
        list($sailor, $boat) = explode(PHP_EOL, trim($row[3]));

        $ret = $this->_report->schema();
        $ret['rank'] = (int) $rank;
        $ret['country'] = trim($coun);
        $ret['sail'] = trim($sail);
        $ret['skipper'] = str_replace('  ', ' ', trim($sailor));
        $ret['boat'] = trim($boat);
        $ret['source'] = basename($file);

        $ret['id'] = date('Ymd-Hi', $ts);
        $ret['date'] = date('Y-m-d', $ts);
        $ret['timestamp'] = $ts;
        $idxExtra = 0;
        if (count($row) === 22) {
            $idxExtra = 1;
        }
        // Since 20170111_1800 there is 1 column less...
        /*
        if (null !== $row[3 + $idxExtra]) {
            $_ts = $this->_getArrivalDate($row[3 + $idxExtra]);

            $ret['timestamp'] = $_ts;
            $ret['time'] = date('H:i', $_ts);
            $ret['date'] = date('Y-m-d', $_ts);
            $ret['lat_dms'] = self::DECtoDMS($this->race['arrival_lat']);
            $ret['lon_dms'] = self::DECtoDMS($this->race['arrival_lon']);
            $ret['lat_dec'] = $this->race['arrival_lat'];
            $ret['lon_dec'] = $this->race['arrival_lon'];

            $ret['has_arrived'] = true;

            return $ret;
        }
        */

        if (false === preg_match("|(\d{2}):(\d{2})|", $row[4 + $idxExtra], $time)) {
            $time[0] = 0;
        }
        $ret['time'] = $time[0];

        $ret['lat_dms'] = trim($row[5 + $idxExtra]);
        $ret['lon_dms'] = trim($row[6 + $idxExtra]);
        $ret['lat_dec'] = self::DMStoDEC(self::strtoDMS(trim($row[5 + $idxExtra])));
        $ret['lon_dec'] = self::DMStoDEC(self::strtoDMS(trim($row[6 + $idxExtra])));

        $ret['1hour_heading'] = (int) trim($row[7 + $idxExtra], '°');
        $ret['1hour_speed'] = (float) trim($row[8 + $idxExtra], ' kts');
        $ret['1hour_vmg'] = (float) trim($row[9 + $idxExtra], ' kts');
        $ret['1hour_distance'] = (float) trim($row[10 + $idxExtra], ' nm');

        $ret['lastreport_heading'] = (int) trim($row[11 + $idxExtra], '°');
        $ret['lastreport_speed'] = (float) trim($row[12 + $idxExtra], ' kts');
        $ret['lastreport_vmg'] = (float) trim($row[13 + $idxExtra], ' kts');
        $ret['lastreport_distance'] = (float) trim($row[14 + $idxExtra], ' nm');

        $ret['24hour_heading'] = (int) trim($row[15 + $idxExtra], '°');
        $ret['24hour_speed'] = (float) trim($row[16 + $idxExtra], ' kts');
        $ret['24hour_vmg'] = (float) trim($row[17 + $idxExtra], ' kts');
        $ret['24hour_distance'] = (float) trim($row[18 + $idxExtra], ' nm');

        $ret['dtf'] = (float) trim($row[19 + $idxExtra], ' nm');
        $ret['dtl'] = (float) trim($row[20 + $idxExtra], ' nm');

        return $ret;
    }

    protected function _getArrivalDate($date)
    {
        $s = '.*? : (.*?)/(.*?)/(.*?) (.*?) UTC .*?';
        preg_match('|' . $s . '|s', $date, $match);

        return strtotime($match[3] . '-' . $match[2] . '-' . $match[1] . ' ' . $match[4] . ' UTC');
    }

    protected function _getDate($data)
    {
        $date = $data[2][1];
        // Classement du 06/11/2016 à 18:00:00 FR
        $s = 'Classement du (.*?)/(.*?)/(.*?) à (.*?) FR';
        preg_match('|' . $s . '|s', $date, $match);

        return strtotime($match[3] . '-' . $match[2] . '-' . $match[1] . ' ' . $match[4] . ' UTC');
    }
}
