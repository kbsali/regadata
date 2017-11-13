<?php

namespace Service\Xls;

class Tjv2017Xls extends XlsManager implements XlsManagerInterface
{
    public $ts;

    public function listMissingXlsx()
    {
        $html = file_get_contents('https://www.transatjacquesvabre.org/fr/historique-des-classements');
        // <a href="/rankings/ClasExcel_2017_11_08_10_11_00.xls" class="doclist__link" download>
        $s = '<a href="/rankings/(.*?)" class="doclist__link" download';
        preg_match_all('|' . $s . '|s', $html, $matches);

        $ret = [];
        foreach ($matches[1] as $xlsx) {
            if ($xlsx == 'ClasExcel_2017_11_05_17_11_00.xls') {
                continue;
            }
            if (!file_exists($this->xlsDir . '/' . $xlsx)) {
                $ret[] = $xlsx;
            }
        }

        return $ret;
    }

    public function xls2mongo($file = null, $force = false)
    {
        require __DIR__ . '/../../Util/Spreadsheet_Excel_Reader.php';
        require __DIR__ . '/../../Util/SpreadsheetReader_XLS.php';

        $this->boats = $this->_sails->findBy('id');
        $tmp = [];
        foreach ($this->boats as $key => $value) {
            $tmp[strtolower($key)] = $value;
        }
        $this->boats = $tmp;

        $xlsxs = glob(null === $file ? $this->xlsDir . '/*' : $file);
        sort($xlsxs);
        $i = 0;
        $total = $yesterday = [];
        foreach ($xlsxs as $xlsx) {
            echo $xlsx . PHP_EOL;
            ++$i;
            try {
                $sheets = [
                    'class40' => 0,
                    'multi50' => 1,
                    'imoca' => 2,
                    'ultimes' => 3,
                ];
                $this->ts = null;
                foreach ($sheets as $class => $sheetId) {
                    $data = new \SpreadsheetReader_XLS($xlsx, ['sheet' => $sheetId]);
                    foreach ($data as $row) {
                        $this->_getDate($row);
                        if (null === $this->ts) {
                            continue;
                        }
                        if (false === $r = $this->_cleanRow($row, $this->ts, $xlsx)) {
                            continue;
                        }
                        $r['class'] = $class;

                        if (!isset($total[$r['sail']])) {
                            $total[$r['sail']] = 0;
                        }
                        $total[$r['sail']] += $r['lastreport_distance'];
                        $r['total_distance'] = $total[$r['sail']];
                        $r['dtl_diff'] = isset($yesterday[$r['sail']]) && !isset($r['has_arrived']) ? $r['dtl'] - $yesterday[$r['sail']]['dtl'] : 0;
                        $r['color'] = $this->_misc->getColor($r['sail']);
                        $yesterday[$r['sail']] = $r;
                        try {
                            $this->_report->insert($r, $force);
                        } catch (\MongoCursorException $e) {
                            // echo $e->getMessage().PHP_EOL;
                        }
                    }
                }
            } catch (\Exception $e) {
                echo 'WHAT? ' . $e->getMessage() . PHP_EOL;
                continue;
            }
        }
    }

    private function _cleanRow($row, $ts, $file)
    {
        $rank = trim($row[0]);
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
        // var_dump($row);
        /* --- IN ---
        [0] => 1
        [1] => ACTUAL
        [2] => Yves le Blevec - Kito de Pavant
        [3] => 5359.21
        [4] =>
        [5] => 11/07/2013 15:30:00
        [6] => 49 29.28' N
        [7] => 0 41.79' W
        [8] => 9.8
        [9] => 9.1
        [10] => 277
        [11] => 7.8
        [12] => 267
        [13] =>
        [14] =>
        [15] =>
         */
        $ret = $this->_report->schema();
        $ret['rank'] = (int) $rank;
        $ret['country'] = 'fr';
        $ret['skipper'] = utf8_decode(trim($row[2]));
        $boat = strtolower(trim($row[1]));
        if (!isset($this->boats[ $boat ])) {
            echo ' NOT FOUND - ' . $boat . PHP_EOL;
            ld($boat);
        }
        $ret['sail'] = !isset($this->boats[ $boat ]) ? null : $this->boats[ $boat ]['sail'];
        $ret['skipper'] = !isset($this->boats[ $boat ]) ? null : $this->boats[ $boat ]['skipper'];
        $ret['boat'] = !isset($this->boats[ $boat ]) ? null : $this->boats[ $boat ]['boat'];
        $ret['source'] = basename($file);
        $ret['id'] = date('Ymd-Hi', $ts);

        $ret['lat_dms'] = trim($row[6]);
        $ret['lon_dms'] = trim($row[7]);

        $ret['date'] = date('Y-m-d', $ts);
        $ret['time'] = date('H:i', $ts);
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

        $ret['1hour_speed'] = (float) trim($row[8]);
        $ret['1hour_vmg'] = (float) trim($row[9]);
        $ret['1hour_heading'] = (int) trim($row[10]);
        // $ret['1hour_distance'] = (float) trim($row[11]);

        $ret['lastreport_vmg'] = (float) trim($row[11]);
        $ret['lastreport_heading'] = (int) trim($row[12]);
        // $ret['lastreport_speed']    = (float) trim($row[11]);
        // $ret['lastreport_distance'] = (float) trim($row[15]);

        $ret['24hour_vmg'] = (int) trim($row[13]);
        $ret['24hour_distance'] = (float) trim($row[14]);
        if ($ret['24hour_distance'] > 0) {
            $ret['24hour_speed'] = $ret['24hour_distance'] / 24;
        }

        $ret['dtf'] = (float) trim($row[3]);
        $ret['dtl'] = (float) trim($row[4]);

        return $ret;
    }

    protected function _getDate($data)
    {
        if (null !== $this->ts) {
            return false;
        }
        if (false === strpos($data[0], 'Date retenue pour')) {
            return false;
        }
        // Imoca - Date retenue pour le calcul du classement intermédiaire estimé à : 13/11/17 20:00 Fr
        $s = ': (.*?)/(.*?)/(.*?) (.*?) Fr';
        preg_match(sprintf('|%s|s', $s), $data[0], $match);
        $date = sprintf('2017-%s-%s %s', $match[2], $match[1], $match[4]);
        $this->ts = strtotime($date);
    }
}
