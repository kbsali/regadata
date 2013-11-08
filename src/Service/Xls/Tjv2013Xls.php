<?php

namespace Service\Xls;

class Tjv2013Xls extends XlsManager implements XlsManagerInterface
{
    public $ts;

    public function listMissingXlsx() {}

    protected function extractLastSaveDT($file)
    {
        if(!file_exists($file)) {
            return false;
        }
        $output = exec('/usr/bin/file ' . $file);
        $outputs = explode(', ', $output);
        foreach ($outputs as $v) {
            if(false !== strpos($v, 'Last Saved Time')) {
                list($bla, $dt) = explode(':', $v, 2);
                return strtotime($dt);
            }
        }
        return false;
    }

    public function renameFiles()
    {
        $xlsxs = glob($this->xlsDir.'/*');
        foreach ($xlsxs as $xlsx) {
            if(false === $mtime = $this->extractLastSaveDT($xlsx)) {
                return false;
            }
            $f = $this->xlsDir.'/'.date('Ymd-Hi', $mtime).'.xls';
            if(!file_exists($f)) {
                echo 'renaming '.$xlsx. ' to '. $f.PHP_EOL;
                rename($xlsx, $f);
            } else {
                unlink($xlsx);
            }
        }
    }

    public function downloadXlsx()
    {
        $tmpFile = $this->xlsDir.'/tmp.xls';

        file_put_contents(
            $tmpFile,
            file_get_contents(
                'http://www.transat-jacques-vabre.com/sites/default/files/classement/classement.xls'
            )
        );
        if(false === $mtime = $this->extractLastSaveDT($tmpFile)) {
            return false;
        }
        $f = $this->xlsDir.'/'.date('Ymd-Hi', $mtime).'.xls';
        if(!file_exists($f)) {
            rename($tmpFile, $f);
        } else {
            unlink($tmpFile);
        }
    }

    public function xls2mongo($file = null, $force = false)
    {
        require(__DIR__.'/../../Util/Spreadsheet_Excel_Reader.php');
        require(__DIR__.'/../../Util/SpreadsheetReader_XLS.php');

        $this->boats = $this->_sails->findBy('id');
        foreach ($this->boats as $key => $value) {
            $tmp[strtolower($key)] = $value;
        }
        $this->boats = $tmp;

        $xlsxs = glob(null === $file ? $this->xlsDir.'/*' : $file);
        sort($xlsxs);
        $i = 0;
        $total = $yesterday = array();
        foreach ($xlsxs as $xlsx) {
            echo $xlsx.PHP_EOL;
            $i++;
            try {
                $sheets = array(
                    'class40' => 0,
                    'multi50' => 1,
                    'imoca'   => 2,
                    'mod70'   => 3,
                );
                foreach ($sheets as $class => $sheetId) {
                    $data = new \SpreadsheetReader_XLS($xlsx, array('sheet' => $sheetId));
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
                        $total[$r['sail']]     += $r['lastreport_distance'];
                        $r['total_distance']   = $total[$r['sail']];
                        $r['dtl_diff']         = isset($yesterday[$r['sail']]) && !isset($r['has_arrived']) ? $r['dtl'] - $yesterday[$r['sail']]['dtl'] : 0;
                        $r['color']            = $this->_misc->getColor($r['sail']);
                        $yesterday[$r['sail']] = $r;
                        try {
                            $this->_report->insert($r, $force);
                        } catch (\MongoCursorException $e) {
                            // echo $e->getMessage().PHP_EOL;
                        }
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
        $rank = trim($row[0]);
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
        $ret['rank']    = (int) $rank;
        $ret['country'] = 'fr';
        $ret['skipper'] = utf8_decode(trim($row[2]));
        $boat           = strtolower(utf8_decode(trim($row[1])));
        if(!isset($this->boats[ $boat ])){
            echo ' NOT FOUND - '.$boat.PHP_EOL;
            ld($boat);
        }
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
        // Multi50 - Date retenue pour le calcul du classement intermiaire estim: 07/11/13 15:30 UTC
        $s = ': (.*?)/(.*?)/(.*?) (.*?) UTC';
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
        // preg_match("|(\d) (\d{2}).(\d{2})'([A-Z]{1})$|s", $str, $matches);
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
