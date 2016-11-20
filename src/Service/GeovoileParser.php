<?php

namespace Service;

class GeovoileParser
{
    private $x, $_report;

    public function __construct($report)
    {
        $this->_report = $report;
    }

    public function setXml($f)
    {
        $tmp = glob($f);
        if (1 !== count($f)) {
            throw new \Exception($f . ' not found');
        }
        $this->x = simplexml_load_file($tmp[0]);
    }

    /*
    <reports>
      <report id="1" date="2013/02/06 12:50:25Z">
        <v i="1" st="" d="37363" s="266" c="222" o="0" />
        <v i="255" st="" d="-134" s="257" c="216" o="0" />
      </report>
    */
    public function getReports()
    {
        $ret = [];
        foreach ($this->x->reports->report as $k => $report) {
            $reportId = (int) $report->attributes()->id;
            $date = (string) $report->attributes()->date; // 2013/02/06 13:18:00Z
            $dt = \DateTime::createFromFormat('Y/m/d H:i:sZ', $date, new \DateTimeZone('UTC'));
            $dt->setTimeZone(new \DateTimeZone('Europe/Paris'));

            foreach ($report->v as $k => $boat) {
                $boatId = (int) $boat->attributes()->i;
                // if (1 !== $boatId) {
                //     continue;
                // }

                $tmp = [
                    'boatId' => $boatId,
                    'date' => $dt->format('Y-m-d'),
                    'time' => $dt->format('H:i'),
                    'dt' => $dt->format('Y-m-d H:i:s'),
                    'timestamp' => $dt->getTimestamp(),
                    'status' => (string) $boat->attributes()->st,
                    'speed' => (int) $boat->attributes()->s / 10,
                    'dtf' => (int) $boat->attributes()->d / 10,
                    'dtl_diff' => (int) $boat->attributes()->l / 10,
                    'heading' => (int) $boat->attributes()->c,
                    'offset' => (int) $boat->attributes()->o,
                ];
                if ($boatId === 17) {
                    echo __METHOD__ . ' ' . $tmp['boatId'] . '-' . $tmp['timestamp'] . '-' . $tmp['dt'] . PHP_EOL;
                }
                $ret[$boatId][ $dt->getTimestamp() ] = $tmp;
            }
        }

        return $ret;
    }

    public function getTracks()
    {
        $ret = [];
        foreach ($this->x->tracks->track as $track) {
            $boatId = (int) $track->attributes()->id;
            // if (1 !== $boatId) {
            //     return false;
            // }
            $_points = explode(';', $track);
            $lat = $lon = $ts = 0;
            foreach ($_points as $_point) {
                $tmp = explode(',', $_point);
                $lat += (int) $tmp[0] / 100000;
                $lon += (int) $tmp[1] / 100000;
                $ts += (int) $tmp[2];

                $dt = new \DateTime();
                $dt->setTimestamp($ts);

                $tmp = [
                    'boatId' => $boatId,
                    'date' => $dt->format('Y-m-d'),
                    'time' => $dt->format('H:i'),
                    'dt' => $dt->format('Y-m-d H:i:s'),
                    'timestamp' => $dt->getTimestamp(),
                    'lat_dec' => $lat,
                    'lon_dec' => $lon,
                    'lat_dms' => self::DECtoDMS($lat),
                    'lon_dms' => self::DECtoDMS($lon),
                ];
                echo __METHOD__ . ' ' . $tmp['boatId'] . '-' . $tmp['timestamp'] . '-' . $tmp['dt'] . PHP_EOL;
                $ret[$boatId][ $dt->getTimestamp() ] = $tmp;
            }

            return $ret;
        }

        return $ret;
    }

    public function merge($a, $b)
    {
        $ret = [];
        $report = $this->_report->schema();
        foreach ($a as $boatId => $_reports) {
            foreach ($_reports as $ts => $_report) {
                if (!isset($b[$boatId][$ts])) {
                    continue;
                }
                $tmp = $_report + $b[$boatId][$ts];
                $ret[$boatId][$ts] = $tmp;
            }
        }

        return $ret;
    }

    public static function DECtoDMS($dec)
    {
        if (!strpos($dec, '.')) {
            $dec = $dec . '.0';
        }
        $vars = explode('.', $dec);
        $deg = $vars[0];
        $tempma = '0.' . $vars[1];
        $tempma = $tempma * 3600;
        $min = floor($tempma / 60);
        $sec = $tempma - ($min * 60);

        return $deg . '°' . $min . '.' . round($sec);

        return [
            'deg' => $deg,
            'min' => $min,
            'sec' => $sec,
        ];
    }
}
