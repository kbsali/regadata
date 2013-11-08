<?php

namespace Service\Xls;

interface XlsManagerInterface
{
    public function listMissingXlsx();
    public function downloadXlsx();
    public function xls2mongo($file = null, $force = false);
    public function mongo2json($force = false);

    public static function strtoDMS($str);
}
