<?php

namespace Service\Xls;

class Rdr2014Xls extends XlsManager implements XlsManagerInterface
{
    public $ts, $class;

    public function listMissingXlsx()
    {
        $html = file_get_contents('http://www.routedurhum.com/fr/s11_classements/s11p02_all_class.php');
        $s = '<a href="/fr/s11_classements/s11p04_get_xls.php\?no_classement=(.*?)" target="_blank">';
        preg_match_all('|' . $s . '|s', $html, $matches);

        $ret = [];
        foreach ($matches[1] as $xlsx) {
            // ClasExcel_2014_11_02_16_11_00.xls
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

        $this->boats = $this->_sails->findBy('skipper');

        $xlsxs = glob(null === $file ? $this->xlsDir . '/*' : $file);
        sort($xlsxs);
        $i = 0;
        $total = $yesterday = [];
        foreach ($xlsxs as $xlsx) {
            echo $xlsx . PHP_EOL;
            ++$i;
            try {
                $sheets = [
                    'ultime' => 0,
                    'imoca' => 1,
                    'multi50' => 2,
                    'class40' => 3,
                    'rhum' => 4,
                ];
                $this->ts = null;
                $tmpClass = null;
                foreach ($sheets as $class => $sheetId) {
                    $data = new \SpreadsheetReader_XLS($xlsx, ['sheet' => $sheetId]);
                    foreach ($data as $idx => $row) {
                        if ((int) $idx < 5) {
                            continue;
                        }
                        if (null === $tmpClass || $tmpClass !== $class) {
                            $this->ts = null;
                            $tmpClass = $class;
                        }
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
                            // print_R($this->_report->insert($r, $force));
                            $this->_report->insert($r, $force);
                        } catch (\MongoCursorException $e) {
                            // echo $e->getMessage().PHP_EOL;
                        }
                    }
                }
            } catch (\Exception $e) {
                throw new \Exception('WHAT? ' . $e->getMessage());
                continue;
            }
        }
    }

    private function _cleanRow($row, $ts, $file)
    {
        $rank = trim(trim($row[0]), ' ');
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
           [0]: string (1) "1"
           [1]: string (18) "PRINCE DE BRETAGNE"
           [2]: string (17) "Lionel Lemonchoix"
           [3]: string (7) "3492.08"
           [4]: string (1) " "
           [5]: string (19) "11/02/2014 16:36:00"
           [6]: string (11) "48 54.85' N"
           [7]: string (10) "2 56.76' W"
           [8]: float 19.6
           [9]: float 16.9
           [10]: int 306
           [11]: string (1) " "
           [12]: string (1) " "
           [13]: string (1) " "
           [14]: string (0) ""
           [15]: string (0) ""
           [16]: float 46.5
           [17]: float 17.9
         */
        $skipper = utf8_decode(trim($row[2]));
        $lat_dms = trim($row[6]);
        $lon_dms = trim($row[7]);
        $ret = $this->_report->schema(
            [
                'rank' => (int) $rank,
                'skipper' => $skipper,
                'sail' => !isset($this->boats[ $skipper ]) ? null : $this->boats[ $skipper ]['sail'],
                'boat' => !isset($this->boats[ $skipper ]) ? null : $this->boats[ $skipper ]['boat'],
                // 'color'     => $this->_misc->getColor($row['code']),
                'source' => basename($file),
                'id' => date('Ymd-Hi', $ts),
                'date' => date('Y-m-d', $ts),
                'time' => date('H:i', $ts),
                'timestamp' => $ts,

                'lat_dms' => $lat_dms,
                'lon_dms' => $lon_dms,
                'lat_dec' => self::DMStoDEC(self::strtoDMS($lat_dms)),
                'lon_dec' => self::DMStoDEC(self::strtoDMS($lat_dms)),

                'dtf' => (float) trim($row[3]),
                'dtl' => (float) trim($row[4]),

                '1hour_speed' => (float) trim($row[8]),
                '1hour_vmg' => (float) trim($row[9]),
                '1hour_heading' => (int) trim($row[10]),

                'lastreport_vmg' => (float) trim($row[11]),
                'lastreport_heading' => (int) trim($row[12]),

                '24hour_vmg' => (int) trim($row[13]),
                '24hour_distance' => (float) trim($row[14]),
            ]
        );
        // ----------------------------
        if (empty($lat_dms)) {
            $ret['lat_dms'] = self::DECtoDMS($this->race['arrival_lat']);
            $ret['lon_dms'] = self::DECtoDMS($this->race['arrival_lon']);
            $ret['lat_dec'] = $this->race['arrival_lat'];
            $ret['lon_dec'] = $this->race['arrival_lat'];

            $ret['has_arrived'] = true;

            return $ret;
        }
        // ----------------------------
        if ($ret['24hour_distance'] > 0) {
            $ret['24hour_speed'] = $ret['24hour_distance'] / 24;
        }

        return $ret;
    }

    protected function _getDate($data)
    {
        if (isset($this->ts)) {
            return;
        }
        if (false === strpos($data[0], 'Date retenue pour')) {
            return false;
        }
        // "ULTIMES - Date retenue pour le calcul du classement interm�diaire estim� � : 02/11/14 16:36 Locale PARIS "
        $s = ': (.*?)/(.*?)/(.*?) (.*?) Locale';
        preg_match('|' . $s . '|s', $data[0], $match);

        $this->ts = strtotime('20' . $match[3] . '-' . $match[2] . '-' . $match[1] . ' ' . $match[4] . ' UTC');
    }
}
