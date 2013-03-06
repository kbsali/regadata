<?php

namespace Service;

class Geovoile
{
    public $xmlDir, $jsonDir, $_parser, $_report, $_misc, $arrLat, $arrLng, $arrival;

    public function __construct($xmlDir, $jsonDir, $parser, $_report, $_misc, $arrLat, $arrLon, $arrival)
    {
        $root = __DIR__.'/../..';
        $this->xmlDir  = $root.$xmlDir;
        $this->jsonDir = $root.$jsonDir;
        $this->_parser = $parser;
        $this->_report = $_report;
        $this->_misc   = $_misc;
        $this->arrLat  = $arrLat;
        $this->arrLon  = $arrLon;
        $this->arrival = $arrival;
    }

    public function download($staticurl, $updateurl, $race)
    {
        $statichwz = $race.'.static.xml.hwz';
        $this->dlAndUnzip($staticurl, $statichwz, true);

        $updatehwz = $race.'.update.xml.hwz';
        $this->dlAndUnzip($updateurl, $updatehwz, true);
    }

    public function dlAndUnzip($url, $zip, $force = false)
    {
        if(file_exists($this->xmlDir.'/'.$zip) && false === $force) {
            return;
        }
        echo ' - downloading '.$url.PHP_EOL;
        file_put_contents($this->xmlDir.'/'.$zip, file_get_contents($url));

        echo ' - decompressing '.$zip.PHP_EOL;
        $_zip = new \ZipArchive;
        $res = $_zip->open($this->xmlDir.'/'.$zip);
        if (true === $res) {
            $_zip->extractTo($this->xmlDir.'/');
            $_zip->close();
            echo 'ok!';
        }
    }

    public function parse()
    {
        $this->_parser->setXml($this->xmlDir.'/update.xml');
        print_r($this->_parser->merge($this->_parser->getTracks(), $this->_parser->getReports()));
        die;
    }
}