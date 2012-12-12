<?php
require __DIR__.'/../Service/VgXls.php';
$vg = new VgXls('/xls', '/web/json');
$vg->xls2json();