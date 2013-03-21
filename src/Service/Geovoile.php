<?php

namespace Service;

class Geovoile
{
    public $xmlDir, $jsonDir, $_parser, $_report, $_misc, $raceId, $race;

    public function __construct($xmlDir, $jsonDir, $parser, $_report, $_misc, $race)
    {
        $root = __DIR__.'/../..';
        $this->jsonDir = $root.$jsonDir;
        $this->_parser = $parser;
        $this->_report = $_report;
        $this->_misc   = $_misc;
        $this->race    = $race;
        $this->xmlDir  = $root.$xmlDir.'/'.$this->race['id'];
        if(!is_dir($this->xmlDir)) {
            mkdir($this->xmlDir);
        }
    }

    public function download()
    {
        $statichwz = $this->race['id'].'.static.xml.hwz';
        $this->dlAndUnzip($this->race['url_static'], $statichwz, true);

        $updatehwz = $this->race['id'].'.update.xml.hwz';
        $this->dlAndUnzip($this->race['url_update'], $updatehwz, true);
    }

    public function dlAndUnzip($url, $zip, $force = false)
    {
        if(file_exists($this->xmlDir.'/'.$zip) && false === $force) {
            return;
        }
        echo ' - downloading '.$url.' to '.$zip.PHP_EOL;
        file_put_contents($this->xmlDir.'/'.$zip, file_get_contents($url));

        echo ' - decompressing '.$this->xmlDir.'/'.$zip;
        $_zip = new \ZipArchive;
        $res = $_zip->open($this->xmlDir.'/'.$zip);
        if (true === $res) {
            $_zip->extractTo($this->xmlDir.'/');
            $_zip->close();
            echo ' OK!';
        } else {
            echo ' WRONG!';
        }
        echo PHP_EOL;
    }

    public function parse()
    {
        $this->_parser->setXml($this->xmlDir.'/*update.xml');

        // print_R($this->_parser->getReports());die;
        // print_R($this->_parser->getTracks());die;
        print_r($this->_parser->merge(
            $this->_parser->getReports(),
            $this->_parser->getTracks())
        );
        die;
    }
}