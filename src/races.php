<?php

$app['races'] = [
    'rdr2014' => [
        'id' => 'rdr2014',
        'hashtag' => 'RDR2014',
        'showTwailorHashtag' => false,
        'host' => 'rdr2014.regadata.org',
        'domain' => 'regadata.org',
        'ua' => 'UA-328215-6',

        'departure' => 'St-Malo',
        'departure_lat' => '48.6481',
        'departure_lon' => '-2.0075',

        'arrival' => 'Pointe-à-Pitre',
        'arrival_lat' => '16.2411',
        'arrival_lon' => '-61.5331',

        'total_distance' => '3542',

        'start_date' => 'sunday 2 november 2014 14:00 +0100',
        'url_xls' => 'http://www.routedurhum.com/fr/s11_classements/s11p04_get_xls.php?no_classement=%file%',
        'url_map' => 'http://www.routedurhum.com/en/s02_corporate/s02p08_cartographie.php',
        'url_gmap' => 'http://goo.gl/PqNO8X', // https://maps.google.com/?q=http://rdr2014.regadata.org/kml/rdr2014/trace_FULL.kmz
        'parser' => 'geovoile',
        'type' => 'race',
        'tweetUrlFr' => [
            'ultime' => 'goo.gl/roRUFp', // http://rdr2014.regadata.org/fr/reports/latest?mode=ultime&utm_source=twitter&utm_medium=link&utm_campaign=twitter_fr
            'imoca' => 'goo.gl/CLEpAk', // http://rdr2014.regadata.org/fr/reports/latest?mode=imoca&utm_source=twitter&utm_medium=link&utm_campaign=twitter_fr
            'multi50' => 'goo.gl/SQFnUW', // http://rdr2014.regadata.org/fr/reports/latest?mode=multi50&utm_source=twitter&utm_medium=link&utm_campaign=twitter_fr
            'class40' => 'goo.gl/3PpH4l', // http://rdr2014.regadata.org/fr/reports/latest?mode=class40&utm_source=twitter&utm_medium=link&utm_campaign=twitter_fr
            'rhum' => 'goo.gl/CjsIbm', // http://rdr2014.regadata.org/fr/reports/latest?mode=rhum&utm_source=twitter&utm_medium=link&utm_campaign=twitter_fr
        ],
        'tweetUrlEn' => [
            'ultime' => 'goo.gl/5IFzzJ', // http://rdr2014.regadata.org/en/reports/latest?mode=ultime&utm_source=twitter&utm_medium=link&utm_campaign=twitter_en
            'imoca' => 'goo.gl/rI3UET', // http://rdr2014.regadata.org/en/reports/latest?mode=imoca&utm_source=twitter&utm_medium=link&utm_campaign=twitter_en
            'multi50' => 'goo.gl/AtkiSn', // http://rdr2014.regadata.org/en/reports/latest?mode=multi50&utm_source=twitter&utm_medium=link&utm_campaign=twitter_en
            'class40' => 'goo.gl/URpHxW', // http://rdr2014.regadata.org/en/reports/latest?mode=class40&utm_source=twitter&utm_medium=link&utm_campaign=twitter_en
            'rhum' => 'goo.gl/EOKCYg', // http://rdr2014.regadata.org/en/reports/latest?mode=rhum&utm_source=twitter&utm_medium=link&utm_campaign=twitter_en
        ],
        'xls_service' => 'srv.rdr2014xls',
        'xls_service_class' => 'Service\Xls\Rdr2014Xls',
        'showReportFooter' => true,
        'modes' => [
            'ultime' => 'Ultime',
            'imoca' => 'IMOCA',
            'multi50' => 'Multi 50',
            'class40' => 'Class 40',
            'rhum' => 'Rhum',
        ],
        'menu' => [
            'map' => false,
            'documentation' => true,
            'about' => false,
        ],
    ],
    'vor2014-2' => [
        'id' => 'vor2014-2',
        'subid' => 'vor2014',
        'hashtag' => 'VolvoOceanRace',
        'showTwailorHashtag' => false,
        'host' => 'vor2014-leg2.regadata.org',
        'domain' => 'regadata.org',
        'ua' => 'UA-328215-6',
        'leg' => [
            1 => [
                'departure' => 'Cape Town',
                'departure_lat' => '-33.925278',
                'departure_lon' => '18.423889',
                'arrival' => 'Abu Dhabi',
                'arrival_lat' => '24.466667',
                'arrival_lon' => '54.366667',
                'start_date' => 'wednesday November 19 2014 13:00',
                'total_distance' => '6487',
                'url_json' => 'http://www.volvooceanrace.com/en/rdc/VOLVO_WEB_LEG2_2014.json',
            ],
        ],
        'departure' => 'Cape Town',
        'departure_lat' => '-33.925278',
        'departure_lon' => '18.423889',
        'arrival' => 'Abu Dhabi',
        'arrival_lat' => '24.466667',
        'arrival_lon' => '54.366667',
        'start_date' => 'wednesday November 19 2014 13:00',
        'total_distance' => '6125',
        'url_json' => 'http://www.volvooceanrace.com/en/rdc/VOLVO_WEB_LEG2_2014.json',
        'url_map' => 'http://www.volvooceanrace.com/en/dashboard.html',
        'url_gmap' => 'http://goo.gl/f1YNqa', // https://maps.google.com/?q=http://vor2014-leg1.regadata.org/kml/vor2014-1/trace_FULL.kmz
        'type' => 'race',
        'tweetUrlFr' => 'goo.gl/mT927u', // http://vor2014-leg2.regadata.org/fr/reports/latest?utm_source=twitter&utm_medium=link&utm_campaign=twitter_fr
        'tweetUrlEn' => 'goo.gl/KOIYp3', // http://vor2014-leg2.regadata.org/en/reports/latest?utm_source=twitter&utm_medium=link&utm_campaign=twitter_en
        'xls_service' => 'srv.vor2014json',
        'xls_service_class' => 'Service\Xls\Vor2014Json',
        'showReportFooter' => true,
        'modes' => false,
        'menu' => [
            'map' => false,
            'documentation' => true,
            'about' => false,
        ],
    ],
    'vor2014-1' => [
        'id' => 'vor2014-1',
        'subid' => 'vor2014',
        'hashtag' => 'VolvoOceanRace',
        'showTwailorHashtag' => false,
        'host' => 'vor2014-leg1.regadata.org',
        'domain' => 'regadata.org',
        'ua' => 'UA-328215-6',
        'leg' => [
            1 => [
                'departure' => 'Alicante',
                'departure_lat' => '38.345278',
                'departure_lon' => '-0.483056',
                'arrival' => 'Cape Town',
                'arrival_lat' => '-33.925278',
                'arrival_lon' => '18.423889',
                'start_date' => 'saturday 11 october 2014 13:00',
                'total_distance' => '6487',
                'url_json' => 'http://www.volvooceanrace.com/en/rdc/VOLVO_WEB_LEG1_2014.json',
            ],
        ],
        'departure' => 'Alicante',
        'departure_lat' => '38.345278',
        'departure_lon' => '-0.483056',
        'arrival' => 'Cape Town',
        'arrival_lat' => '-33.925278',
        'arrival_lon' => '18.423889',
        'start_date' => 'saturday 11 october 2014 13:00',
        'total_distance' => '6487',
        'url_json' => 'http://www.volvooceanrace.com/en/rdc/VOLVO_WEB_LEG1_2014.json',
        'url_map' => 'http://www.volvooceanrace.com/en/dashboard.html',
        'url_gmap' => 'http://goo.gl/zrO4cK', // https://maps.google.com/?q=http://vor2014-leg1.regadata.org/kml/vor2014-1/trace_FULL.kmz
        'type' => 'race',
        'tweetUrlFr' => 'goo.gl/xnSqIE', // http://vor2014-leg1.regadata.org/fr/reports/latest?utm_source=twitter&utm_medium=link&utm_campaign=twitter_fr
        'tweetUrlEn' => 'goo.gl/yPyxn8', // http://vor2014-leg1.regadata.org/en/reports/latest?utm_source=twitter&utm_medium=link&utm_campaign=twitter_en
        'xls_service' => 'srv.vor2014json',
        'xls_service_class' => 'Service\Xls\Vor2014Json',
        'showReportFooter' => true,
        'modes' => false,
        'menu' => [
            'map' => false,
            'documentation' => true,
            'about' => false,
        ],
    ],
    'tjv2013' => [
        'id' => 'tjv2013',
        'hashtag' => 'TJV2013',
        'showTwailorHashtag' => false,
        'host' => 'tjv2013.regadata.org',
        'domain' => 'regadata.org',
        'ua' => 'UA-328215-6',

        'departure' => 'Le Havre',
        'departure_lat' => '49.4900000',
        'departure_lon' => '00.1000000',

        // 'arrival'            => 'Itajaí',
        // 'arrival_lat'        => '-26.9077778',
        // 'arrival_lon'        => '-048.6619444',

        'arrival' => 'Roscoff',
        'arrival_lat' => '48.7162, ',
        'arrival_lon' => '-3.9652',

        'total_distance' => '5450',

        'start_date' => 'sunday 3 november 2013 13:02',
        // 'url_update'         => 'http://transat-jacquesvabre.geovoile.com/2013/shared/data/race/update.hwz',
        // 'url_static'         => 'http://transat-jacquesvabre.geovoile.com/2013/shared/data/race/static.hwz',
        'url_xls' => 'http://www.transat-jacques-vabre.com/sites/default/files/classement/%file%',
        'url_map' => 'http://tracking.transat-jacques-vabre.com/fr/',
        'url_gmap' => 'http://goo.gl/GjX18r', // https://maps.google.com/?q=http://tjv2013.regadata.org/kml/tjv2013/trace_FULL.kmz
        'parser' => 'geovoile',
        'type' => 'race',
        'tweetUrlFr' => [
            'class40' => 'goo.gl/rHXxtC', // http://tjv2013.regadata.org/fr/reports/latest?mode=class40&utm_source=twitter&utm_medium=link&utm_campaign=twitter_fr
            'multi50' => 'goo.gl/OJQaY5', // http://tjv2013.regadata.org/fr/reports/latest?mode=multi50&utm_source=twitter&utm_medium=link&utm_campaign=twitter_fr
            'imoca' => 'goo.gl/WsL1on', // http://tjv2013.regadata.org/fr/reports/latest?mode=imoca&utm_source=twitter&utm_medium=link&utm_campaign=twitter_fr
            'mod70' => 'goo.gl/rckAEP', // http://tjv2013.regadata.org/fr/reports/latest?mode=mod70&utm_source=twitter&utm_medium=link&utm_campaign=twitter_fr
        ],
        'tweetUrlEn' => [
            'class40' => 'goo.gl/myqpFI', // http://tjv2013.regadata.org/en/reports/latest?mode=class40&utm_source=twitter&utm_medium=link&utm_campaign=twitter_en
            'multi50' => 'goo.gl/I7Y8eV', // http://tjv2013.regadata.org/en/reports/latest?mode=multi50&utm_source=twitter&utm_medium=link&utm_campaign=twitter_en
            'imoca' => 'goo.gl/aTkEjP', // http://tjv2013.regadata.org/en/reports/latest?mode=imoca&utm_source=twitter&utm_medium=link&utm_campaign=twitter_en
            'mod70' => 'goo.gl/BmvVtR', // http://tjv2013.regadata.org/en/reports/latest?mode=mod70&utm_source=twitter&utm_medium=link&utm_campaign=twitter_en
        ],
        'xls_service' => 'srv.tjv2013xls',
        'xls_service_class' => 'Service\Xls\Tjv2013Xls',
        'showReportFooter' => true,
        'modes' => [
            'class40' => 'Class 40',
            'multi50' => 'Multi 50',
            'imoca' => 'IMOCA',
            'mod70' => 'MOD 70',
        ],
        'menu' => [
            'map' => true,
            'documentation' => true,
            'about' => false,
        ],
    ],
    'mini2013' => [
        'id' => 'mini2013',
        'hashtag' => 'MiniTransat',
        'showTwailorHashtag' => false,
        'host' => 'mini2013.regadata.org',
        'domain' => 'regadata.org',
        'ua' => 'UA-328215-6',
        'departure' => 'Sada',
        'departure_lat' => '43.3609',
        'departure_lon' => '-8.2465',
        'arrival' => 'Pointe-à-Pitre',
        'arrival_lat' => '16.2411',
        'arrival_lon' => '-61.5331',
        'total_distance' => '1250',

        // 'arrival'            => 'Lanzarote',
        // 'arrival_lat'        => '19.6716700',
        // 'arrival_lon'        => '-99.3350000',
        // 'total_distance'     => '2770',

        'start_date' => 'tuesday 29 october 2013 13:00',
        // 'url_update'         => 'http://transat-jacquesvabre.geovoile.com/2013/shared/data/race/update.hwz',
        // 'url_static'         => 'http://transat-jacquesvabre.geovoile.com/2013/shared/data/race/static.hwz',
        'url_xls' => 'http://www.minitransat.fr/classement/historique/doc/%file%',
        'url_map' => 'http://www.minitransat.fr/cartographie',
        'url_gmap' => 'http://goo.gl/PJpyA0', // https://maps.google.com/?q=http://mini2013.regadata.org/kml/mini2013/trace_FULL.kmz
        'parser' => 'geovoile',
        'type' => 'race',
        'tweetUrlFr' => [
            'proto' => 'goo.gl/3gRbFk', // http://mini2013.regadata.org/fr/reports/latest?utm_source=twitter&utm_medium=link&utm_campaign=twitter_fr?mode=proto
            'serie' => 'goo.gl/G2Glis', // http://mini2013.regadata.org/fr/reports/latest?utm_source=twitter&utm_medium=link&utm_campaign=twitter_fr?mode=serie
        ],
        'tweetUrlEn' => [
            'proto' => 'goo.gl/bVAYhX', // http://mini2013.regadata.org/en/reports/latest?utm_source=twitter&utm_medium=link&utm_campaign=twitter_en?mode=proto
            'serie' => 'goo.gl/aaHJri', // http://mini2013.regadata.org/en/reports/latest?utm_source=twitter&utm_medium=link&utm_campaign=twitter_en?mode=serie
        ],
        'xls_service' => 'srv.mini2013xls',
        'xls_service_class' => 'Service\Xls\Mini2013Xls',
        'showReportFooter' => true,
        'modes' => [
            'proto' => 'Proto',
            'serie' => 'Série',
        ],
        'menu' => [
            'map' => true,
            'documentation' => true,
            'about' => false,
        ],
    ],
    'tbm2013' => [
        'id' => 'tbm2013',
        'hashtag' => 'TransatBM',
        'showTwailorHashtag' => false,
        'host' => 'tbm2013.regadata.org',
        'domain' => 'regadata.org',
        'ua' => 'UA-328215-6',
        'departure' => 'Brest',
        'departure_lat' => '48.390604',
        'departure_lon' => '-4.486901',
        'arrival' => 'Fort-de-France',
        'arrival_lat' => '14.603518',
        'arrival_lon' => '-61.066818',
        'start_date' => 'sunday 17 march 2013 13:00',
        'total_distance' => '3500',
        'url_update' => 'http://transat-bretagnemartinique.geovoile.com/2013/shared/data/race/leg1.update.hwz',
        'url_static' => 'http://transat-bretagnemartinique.geovoile.com/2013/shared/data/race/leg1.static.hwz',
        'url_xls' => 'http://www.transat-bretagnemartinique.com/fr/s10_classement/s10p04_get_xls.php?no_classement=%file%',
        'url_map' => 'http://transat-bretagnemartinique.geovoile.com/2013/',
        'url_gmap' => 'http://goo.gl/BE59RZ', // https://maps.google.com/?q=http://tbm2013.regadata.org/kml/tbm2013/trace_FULL.kmz
        'parser' => 'geovoile',
        'type' => 'race',
        'tweetUrlFr' => 'goo.gl/rDb2z', // http://tbm2013.regadata.org/fr/reports/latest?utm_source=twitter&utm_medium=link&utm_campaign=twitter_fr
        'tweetUrlEn' => 'goo.gl/PXc96', // http://tbm2013.regadata.org/en/reports/latest?utm_source=twitter&utm_medium=link&utm_campaign=twitter_en
        'xls_service' => 'srv.tbmxls',
        'xls_service_class' => 'Service\Xls\Tbm2013Xls',
        'showReportFooter' => true,
        'modes' => false,
        'menu' => [
            'map' => true,
            'documentation' => true,
            'about' => false,
        ],
    ],
    'vg2012' => [
        'id' => 'vg2012',
        'hashtag' => 'VG2012',
        'showTwailorHashtag' => false,
        'host' => 'vg2012.saliou.name',
        'domain' => 'saliou.name',
        'ua' => 'UA-328215-5',
        'departure' => "Les Sables-d'Olonne",
        'departure_lat' => '46.4972',
        'departure_lon' => '-1.7833',
        'arrival' => "Les Sables-d'Olonne",
        'arrival_lat' => '46.4972',
        'arrival_lon' => '-1.7833',
        'start_date' => 'saturday 10 november 2012 15:02',
        'total_distance' => '24016',
        'url_xls' => 'http://tracking2012.vendeeglobe.org/download/%file%',
        'url_map' => 'http://tracking2012.vendeeglobe.org/fr/',
        'url_gmap' => 'http://goo.gl/jCUHeK', // https://maps.google.com/?q=http://vg2012.saliou.name/kml/vg2012/trace_FULL.kmz
        'parser' => 'vg',
        'type' => 'race',
        'tweetUrlFr' => 'goo.gl/B8yKv', // http://vg2012.saliou.name/fr/reports/latest?utm_source=twitter&utm_medium=link&utm_campaign=twitter_fr
        'tweetUrlEn' => 'goo.gl/3VJyD', // http://vg2012.saliou.name/en/reports/latest?utm_source=twitter&utm_medium=link&utm_campaign=twitter_en
        'showReportFooter' => true,
        'xls_service' => 'srv.vgxls',
        'xls_service_class' => 'Service\Xls\Vg2012Xls',
        'modes' => false,
        'menu' => [
            'map' => true,
            'documentation' => true,
            'about' => true,
        ],
    ],
    'vg2016' => [
        'id' => 'vg2016',
        'hashtag' => 'VG2016',
        'showTwailorHashtag' => false,
        'host' => 'vg2016.regadata.org',
        'domain' => 'saliou.name',
        'ua' => 'UA-328215-6',
        'departure' => "Les Sables-d'Olonne",
        'departure_lat' => '46.4972',
        'departure_lon' => '-1.7833',
        'arrival' => "Les Sables-d'Olonne",
        'arrival_lat' => '46.4972',
        'arrival_lon' => '-1.7833',
        'start_date' => 'sunda 06 november 2016 15:02',
        'total_distance' => '24016',
        'url_xls' => 'http://www.vendeeglobe.org/download-race-data/%file%',
        'url_map' => 'http://tracking2016.vendeeglobe.org/hp5ip0/',
        'url_gmap' => 'goo.gl/DQWUDO', // https://maps.google.com/?q=http://vg2016.regadata.org/kml/vg2016/trace_FULL.kmz
        'parser' => 'vg',
        'type' => 'race',
        'tweetUrlFr' => 'goo.gl/E3QgCY', // http://vg2016.regadata.org/fr/reports/latest?utm_source=twitter&utm_medium=link&utm_campaign=twitter_fr
        'tweetUrlEn' => 'goo.gl/kXjss0', // http://vg2016.regadata.org/en/reports/latest?utm_source=twitter&utm_medium=link&utm_campaign=twitter_fr
        'showReportFooter' => true,
        'xls_service' => 'srv.vgxls',
        'xls_service_class' => 'Service\Xls\Vg2016Xls',
        'modes' => false,
        'menu' => [
            'map' => true,
            'documentation' => true,
            'about' => true,
        ],
    ],
    'guochuansailing-2012' => [
        'id' => 'guochuansailing-2012',
        'host' => 'guochuansailing-2012.regadata.org',
        'departure' => 'Sanghai',
        'departure_lat' => '46',
        'departure_lon' => '-1',
        'arrival' => 'Sanghai',
        'arrival_lat' => '47',
        'arrival_lon' => '-1',
        'start_date' => 'saturday 10 november 2012 15:02',
        'url_update' => 'http://guochuansailing.geovoile.com/roundtheworld/2012/shared/data/race/update.hwz',
        'url_static' => 'http://guochuansailing.geovoile.com/roundtheworld/2012/shared/data/race/static.hwz',
        'parser' => 'geovoile',
        'type' => 'record',
        'modes' => false,
    ],
    'soldini-2012' => [
        'id' => 'soldini-2012',
        'host' => 'soldini-2012.regadata.org',
        'departure' => 'New York',
        'departure_lat' => '46',
        'departure_lon' => '-1',
        'arrival' => 'San Francisco',
        'arrival_lat' => '47',
        'arrival_lon' => '-1',
        'start_date' => 'saturday 10 november 2012 15:02',
        'url_update' => 'http://soldini.geovoile.com/newyorksanfrancisco/2012/private/data/update.hwz',
        'url_static' => 'http://soldini.geovoile.com/newyorksanfrancisco/2012/private/data/static.hwz',
        'parser' => 'geovoile',
        'type' => 'record',
        'modes' => false,
    ],
];
