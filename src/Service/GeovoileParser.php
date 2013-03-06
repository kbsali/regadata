<?php

namespace Service;

class GeovoileParser {

    private $x;

    public function __construct()
    {

    }

    public function setXml($f)
    {
        $this->x = simplexml_load_file($f);
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
        $ret = array();
        foreach ($this->x->reports->report as $k => $report) {

            $boatId = (int)$report->v->attributes()->i;
            if(1 !== $boatId) {
                continue;
            }
            $reportId = (int)$report->attributes()->id;
            $date     = (string)$report->attributes()->date; // 2013/02/06 13:18:00Z
            $dt       = \DateTime::createFromFormat('Y/m/d H:i:sZ', $date, new \DateTimeZone('UTC'));
            $dt->setTimeZone(new \DateTimeZone('Europe/Paris'));

            $tmp = array(
                'boatId'    => $boatId,
                'date'      => $dt->format('Y-m-d'),
                'time'      => $dt->format('H:i'),
                'dt'        => $dt->format('Y-m-d H:i:s'),
                'timestamp' => $dt->getTimestamp(),
                'status'    => (string)$report->v->attributes()->st,
                'speed'     => (int)$report->v->attributes()->s / 10,
                'dtf'       => (int)$report->v->attributes()->d / 10,
                'dtl_diff'  => (int)$report->v->attributes()->l / 10,
                'heading'   => (int)$report->v->attributes()->c,
                'offset'    => (int)$report->v->attributes()->o,
            );
            $ret[ $dt->getTimestamp() ] = $tmp;
         }
         return $ret;
    }

    public function getTracks()
    {
        $tracks = $this->x->tracks->track;
        $boatId   = (int)$tracks->attributes()->id;
        if(1 !== $boatId) {
            return;
        }
        $_tracks = explode(';', $tracks);
        $lat = $lon = $ts = 0;

        $ret = array();
        foreach($_tracks as $track) {
            $tmp = explode(',', $track);
            $lat += (int)$tmp[0] / 100000;
            $lon += (int)$tmp[1] / 100000;
            $ts  += (int)$tmp[2];

            $dt = new \DateTime();
            $dt->setTimestamp($ts);

            $tmp = array(
                'boatId'    => $boatId,
                'date'      => $dt->format('Y-m-d'),
                'time'      => $dt->format('H:i'),
                'dt'        => $dt->format('Y-m-d H:i:s'),
                'timestamp' => $dt->getTimestamp(),
                'lat_dec'   => $lat,
                'lon_dec'   => $lon,
                'lat_dms'   => self::DECtoDMS($lat),
                'lon_dms'   => self::DECtoDMS($lon),
            );
            $ret[ $dt->getTimestamp() ] = $tmp;
        }
        return $ret;
    }

    public function merge($a, $b)
    {
        $ret = array();
        foreach($a as $k => $v) {
            $ret[$k] = $v+$b[$k];
        }
        return $ret;
    }

    public static function DECtoDMS($dec)
    {
        if(!strpos($dec, '.')) {
            $dec = $dec.'.0';
        }
        $vars   = explode('.', $dec);
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
}