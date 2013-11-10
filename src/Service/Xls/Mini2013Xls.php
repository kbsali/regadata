<?php

namespace Service\Xls;

class Mini2013Xls extends XlsManager implements XlsManagerInterface
{
    public $ts, $class;

    public function listMissingXlsx()
    {
        $html = file_get_contents('http://www.minitransat.fr/classement/historique');
        $s = '<a href="/classement/historique/doc/(.*?)">Télécharger</a>';
        preg_match_all('|'.$s.'|s', $html, $matches);

        $ret = array();
        foreach ($matches[1] as $xlsx) {
            // minitransat_20131030_090000
            if (!file_exists($this->xlsDir.'/'.$xlsx)) {
                $ret[] = $xlsx;
            }
        }

        return $ret;
    }

    public function xls2mongo($file = null, $force = false)
    {
        require(__DIR__.'/../../Util/XLSXReader.php');

        $this->boats = $this->_sails->findBy('id');

        $xlsxs = glob(null === $file ? $this->xlsDir.'/*' : $file);
        sort($xlsxs);
        $i = 0;
        $total = $yesterday = array();
        foreach ($xlsxs as $xlsx) {
            $i++;
            try {
                echo $xlsx.PHP_EOL;
                $_xlsx = new \XLSXReader($xlsx);
                $data  = $_xlsx->getSheetData('Proto');
                $ts    = $this->_getDate($data);
                foreach ($data as $row) {
                    $this->_getClass($row);
                    if (false === $r = $this->_cleanRow($row, $ts, $xlsx)) {
                        continue;
                    }
                    $r['class'] = $this->class;
                    if (!isset($total[$r['sail']])) {
                           $total[$r['sail']] = 0;
                    }
                    $total[$r['sail']]     += $r['lastreport_distance'];
                    $r['total_distance']   = $total[$r['sail']];
                    $r['dtl_diff']         = isset($yesterday[$r['sail']]) ? $r['dtl'] - $yesterday[$r['sail']]['dtl'] : 0;
                    $r['color']            = $this->_misc->getColor($r['sail']);
                    $yesterday[$r['sail']] = $r;
                    try {
                        // print_r($this->_report->insert($r, $force));
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
        $rank = trim(trim($row[1], ' '));
        if ('RET' == $rank) {
            return false;
        } else {
            $rank = (int) $rank;
            if (0 === $rank) {
                return false;
            }
        }
        /* --- IN ---
        [86] => Array
        (
            [0] =>
            [1] =>  48
            [2] =>  (682)
            [3] =>  Andrea Iacopini Umpalumpa
            [4] =>
            [5] =>  11:30 FR

            [6] => 48°07.99'N
            [7] => 04°28.78'W

            [8] => 244°
            [9] => 4.4 kts
            [10] => 4.4 kts
            [11] => 4.4 nm

            [12] => 287°
            [13] => 3.0 kts
            [14] => 2.3 kts
            [15] => 6.6 nm

            [16] => 281°
            [17] => 2.8 kts
            [18] => 2.3 kts
            [19] => 5.5 nm

            [20] => 333.4 nm
            [21] => 2.6 nm
            [22] =>
        )
         */
        $ret = $this->_report->schema();
        $ret['rank']    = (int) $rank;
        $boat           = str_replace(array('(', ')'), '', trim($row[2], ' '));
        $ret['sail']    = !isset($this->boats[ $boat ]) ? null : $this->boats[ $boat ]['sail'];
        $ret['skipper'] = !isset($this->boats[ $boat ]) ? null : $this->boats[ $boat ]['skipper'];
        $ret['boat']    = !isset($this->boats[ $boat ]) ? null : $this->boats[ $boat ]['boat'];
        $ret['country'] = substr($ret['sail'], 0, 3);
        $ret['source']  = basename($file);
        $ret['id']      = date('Ymd-Hi', $ts);

        $ret['lat_dms'] = trim($row[6]);
        $ret['lon_dms'] = trim($row[7]);

        $ret['date']      = date('Y-m-d', $ts);
        $ret['time']      = date('H:i', $ts);
        $ret['timestamp'] = $ts;

        // ----------------------------
        // if (empty($ret['lat_dms'])) {
        //     $ret['lat_dms'] = self::DECtoDMS($this->race['arrival_lat']);
        //     $ret['lon_dms'] = self::DECtoDMS($this->race['arrival_lon']);
        //     $ret['lat_dec'] = $this->race['arrival_lat'];
        //     $ret['lon_dec'] = $this->race['arrival_lat'];

        //     $ret['has_arrived'] = true;

        //     return $ret;
        // }
        // ----------------------------

        $ret['lat_dec'] = self::DMStoDEC(self::strtoDMS($ret['lat_dms']));
        $ret['lon_dec'] = self::DMStoDEC(self::strtoDMS($ret['lon_dms']));

        $ret['1hour_heading']  = (int) trim($row[8]);
        $ret['1hour_speed']    = (float) trim($row[9]);
        $ret['1hour_vmg']      = (float) trim($row[10]);
        $ret['1hour_distance'] = (float) trim($row[11]);

        $ret['lastreport_heading']  = (int) trim($row[12]);
        $ret['lastreport_speed']    = (float) trim($row[13]);
        $ret['lastreport_vmg']      = (float) trim($row[14]);
        $ret['lastreport_distance'] = (float) trim($row[15]);

        $ret['24hour_heading']  = (int) trim($row[16]);
        $ret['24hour_speed']    = (float) trim($row[17]);
        $ret['24hour_vmg']      = (float) trim($row[18]);
        $ret['24hour_distance'] = (float) trim($row[19]);

        $ret['dtf'] = (float) trim($row[20]);
        $ret['dtl'] = (float) trim($row[21]);

        return $ret;
    }

    protected function _getClass($row)
    {
        if (false !== strpos($row[1], 'Série')) {
            $this->class = 'serie';
        } elseif (false !== strpos($row[1], 'Proto')) {
            $this->class = 'proto';
        }
    }
    protected function _getDate($data)
    {
        $date = $data[2][1];
        // Classement du 29/10/2013 à 12:00:00 FR
        $s = 'Classement du (.*?)/(.*?)/(.*?) à (.*?) FR';
        preg_match('|'.$s.'|s', $date, $match);

        return strtotime($match[3].'-'.$match[2].'-'.$match[1].' '.$match[4].' UTC');
        // $this->ts = strtotime($match[3].'-'.$match[2].'-'.$match[1].' '.$match[4].' UTC');
    }
}
