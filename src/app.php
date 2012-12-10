<?php

use Symfony\Component\HttpFoundation\Request;

$app = require __DIR__.'/bootstrap.php';

$app->get('/reports/{id}', function ($id) use ($app) {
    $reports = $app['srv.vg']->listJson('reports');
    if ('latest' === $id) {
        $id = str_replace(array('/json/reports/', '.json'), '', $reports[0]);
    }
    $idx = array_search('/json/reports/'.$id.'.json', $app['srv.vg']->listJson('reports'));

    $prev = null;
    if (isset($reports[$idx + 1])) {
        $file = $reports[$idx + 1];
        $prev = str_replace(array('/json', '.json'), '', $file);
    }
    $next = null;
    if (isset($reports[$idx - 1])) {
        $file = $reports[$idx - 1];
        $next = str_replace(array('/json', '.json'), '', $file);
    }
    $last = str_replace(array('/json', '.json'), '', $reports[0]);
    $first = str_replace(array('/json', '.json'), '', end($reports));

    $report = $app['srv.vg']->parseJson('/reports/'.$id.'.json');

    return $app['twig']->render('reports/reports.html.twig', array(
        'r' => current($report),
        'report' => $report,
        'source' => '/json/reports/'.$id.'.json',

        'pagination' => array(
            'first'   => $first,
            'prev'    => $prev,
            'next'    => $next,
            'last'    => $last,
            'current' => abs(count($reports) - $idx),
            'total'   => count($reports),
        )
    ));
});

$app->get('/doc/json', function () use ($app) {
    return $app['twig']->render('doc/json.html.twig', array());
});
$app->get('/doc/json-format', function () use ($app) {
    return $app['twig']->render('doc/json-format.html.twig', array());
});

$app->get('/about', function () use ($app) {
    $reports     = $app['srv.vg']->listJson('reports');
    $first       = str_replace('/json', '', end($reports));
    $firstReport = $app['srv.vg']->parseJson($first);

    return $app['twig']->render('about.html.twig', array(
        'lFull'       => $app['srv.vg']->listJson(),
        'lSail'       => $app['lSail'],
        '_lSail'      => $app['_lSail'],
        'rndSail'     => $app['_lSail'][array_rand($app['_lSail'])],
        'lReport'     => $reports,
        'firstReport' => $firstReport,
    ));
});

function extractSailInfo($arr, $app)
{
    return array(
        'info'             => end($arr),
        'rank'             => $app['srv.vg']->filterBy($arr, 'rank', 1),
        'dtl'              => $app['srv.vg']->filterBy($arr, 'dtl', 1),
        't24hour_distance' => $app['srv.vg']->filterBy($arr, '24hour_distance', 1),
        't24hour_speed'    => $app['srv.vg']->filterBy($arr, '24hour_speed', 1),
    );
}

$app->get('/compare', function (Request $request) use ($app) {
    return $app->redirect('/sail/'.$request->get('sail1').'/'.$request->get('sail2'));
});

$app->get('/sail/{id1}/{id2}', function ($id1, $id2) use ($app) {
    $arr   = $app['srv.vg']->parseJson('/sail/'.$id1.'.json');
    $info1 = extractSailInfo($arr, $app);

    $arr   = $app['srv.vg']->parseJson('/sail/'.$id2.'.json');
    $info2 = extractSailInfo($arr, $app);

    $_lSail2 = array();
    foreach ($app['_lSail'] as $s) {
        $_lSail2[substr($s, 6)] = substr($s, 6);
    }

    return $app['twig']->render('sail/sail_compare.html.twig', array(

        'info1'             => $info1['info'],
        'rank1'             => json_encode(array('label' => $id1, 'data' => $info1['rank'])),
        'dtl1'              => json_encode(array('label' => $id1, 'data' => $info1['dtl'])),
        't24hour_distance1' => json_encode(array('label' => $id1, 'data' => $info1['t24hour_distance'])),
        't24hour_speed1'    => json_encode(array('label' => $id1, 'data' => $info1['t24hour_speed'])),

        'info2'             => $info2['info'],
        'rank2'             => json_encode(array('label' => $id2, 'data' => $info2['rank'])),
        'dtl2'              => json_encode(array('label' => $id2, 'data' => $info2['dtl'])),
        't24hour_distance2' => json_encode(array('label' => $id2, 'data' => $info2['t24hour_distance'])),
        't24hour_speed2'    => json_encode(array('label' => $id2, 'data' => $info2['t24hour_speed'])),

        'sail1'    => $app['html']->dropdown('sail1', $_lSail2, $id1),
        'sail2'    => $app['html']->dropdown('sail2', $_lSail2, $id2, '... avec'),
    ));
});

$app->get('/sail/{id1}', function ($id1) use ($app) {
    $arr   = $app['srv.vg']->parseJson('/sail/'.$id1.'.json');
    $info1 = extractSailInfo($arr, $app);

    $_lSail2 = array();
    foreach ($app['_lSail'] as $s) {
        $_lSail2[substr($s, 6)] = substr($s, 6);
    }

    return $app['twig']->render('sail/sail.html.twig', array(
        'info1'             => $info1['info'],
        'rank1'             => json_encode(array('label' => $id1, 'data' => $info1['rank'])),
        'dtl1'              => json_encode(array('label' => $id1, 'data' => $info1['dtl'])),
        't24hour_distance1' => json_encode(array('label' => $id1, 'data' => $info1['t24hour_distance'])),
        't24hour_speed1'    => json_encode(array('label' => $id1, 'data' => $info1['t24hour_speed'])),

        'sail1'    => $app['html']->dropdown('sail1', $_lSail2, $id1),
        'sail2'    => $app['html']->dropdown('sail2', $_lSail2, null, '... avec'),
    ));
});

$app->get('/', function () use ($app) {
    return $app->redirect('/reports/latest');
});

return $app;
