<?php

namespace Service\Xls;

class Tbm2013Xls extends XlsManager implements XlsManagerInterface
{
    public $ts;

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

    public function xls2mongo($file = null, $force = false)
    {
        require(__DIR__.'/../../Util/Spreadsheet_Excel_Reader.php');
        require(__DIR__.'/../../Util/SpreadsheetReader_XLS.php');

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
                    if (null === $this->ts) {
                        continue;
                    }
                    if (false === $r = $this->_cleanRow($row, $this->ts, $xlsx)) {
                        continue;
                    }
                    if (!isset($total[$r['sail']])) {
                        $total[$r['sail']] = 0;
                    }
                    $total[$r['sail']]     += $r['lastreport_distance'];
                    $r['total_distance']   = $total[$r['sail']];
                    $r['dtl_diff']         = isset($yesterday[$r['sail']]) && !isset($r['has_arrived']) ? $r['dtl'] - $yesterday[$r['sail']]['dtl'] : 0;
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
        $ret['rank']    = (int) $rank;
        $ret['country'] = trim('fr');
        $ret['skipper'] = utf8_decode(trim($row[2]));
        $boat           = trim($row[1]);
        $ret['sail']    = !isset($this->boats[ $boat ]) ? null : $this->boats[ $boat ]['sail'];
        $ret['skipper'] = !isset($this->boats[ $boat ]) ? null : $this->boats[ $boat ]['skipper'];
        $ret['boat']    = !isset($this->boats[ $boat ]) ? null : $this->boats[ $boat ]['boat'];
        $ret['source']  = basename($file);
        $ret['id']      = date('Ymd-Hi', $ts);

        $ret['lat_dms'] = trim($row[6]);
        $ret['lon_dms'] = trim($row[7]);

        $ret['date']      = date('Y-m-d', $ts);
        $ret['time']      = date('H:i', $ts);
        $ret['timestamp'] = $ts;

        // ----------------------------
        if (empty($ret['lat_dms'])) {
            $ret['lat_dms'] = self::DECtoDMS($this->race['arrival_lat']);
            $ret['lon_dms'] = self::DECtoDMS($this->race['arrival_lon']);
            $ret['lat_dec'] = $this->race['arrival_lat'];
            $ret['lon_dec'] = $this->race['arrival_lat'];

            $ret['has_arrived'] = true;

            return $ret;
        }
        // ----------------------------

        $ret['lat_dec'] = self::DMStoDEC(self::strtoDMS($ret['lat_dms']));
        $ret['lon_dec'] = self::DMStoDEC(self::strtoDMS($ret['lon_dms']));

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
        if ($ret['24hour_distance'] > 0) {
            $ret['24hour_speed'] = $ret['24hour_distance'] / 24;
        }

        $ret['dtf'] = (float) trim($row[3]);
        $ret['dtl'] = (float) trim($row[4]);

        return $ret;
    }

    private function _getArrivalDate($date) {}

    private function _getDate($data)
    {
        if (false === strpos($data[0], 'Date retenue pour')) {
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
        if (empty($str)) {
            return array(
                'deg' => 0,
                'min' => 0,
                'sec' => 0,
                'dir' => 0,
            );
        }
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
}
