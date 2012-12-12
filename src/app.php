<?php

use Symfony\Component\HttpFoundation\Request;

$app = require __DIR__.'/bootstrap.php';

$app->get('/{_locale}/reports/{id}', function ($id) use ($app) {
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
        'r'      => current($report),
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

$app->get('/{_locale}/doc/json', function () use ($app) {
    return $app['twig']->render('doc/json.html.twig', array());
});
$app->get('/doc/json-format', function () use ($app) {
    return $app['twig']->render('doc/json-format.html.twig', array());
});

$app->get('/{_locale}/about', function () use ($app) {
    $reports     = $app['srv.vg']->listJson('reports');
    $first       = str_replace('/json', '', end($reports));
    $firstReport = $app['srv.vg']->parseJson($first);

    return $app['twig']->render('about.html.twig', array(
        'rndSail'     => array_rand($app['sk']),
        'reports'     => $app['srv.vg']->getReportsById($reports),
    ));
});

$app->get('/{_locale}/compare', function (Request $request) use ($app) {
    return $app->redirect('/'.$request->getLocale().'/sail/'.$request->get('sail1').'-'.$request->get('sail2'));
});

$app->get('/{_locale}/sail/{ids}', function ($ids) use ($app) {
    $ids = explode('-', $ids);
    $infos = array();
    foreach ($ids as $id) {
        if (false !== $info = $app['srv.vg']->getFullSailInfo($id)) {
            $infos[] = array(
                'info'             => $info['info'],
                'rank'             => json_encode(array('label' => $info['info']['skipper'], 'data' => $info['rank'])),
                'dtl'              => json_encode(array('label' => $info['info']['skipper'], 'data' => $info['dtl'])),
                't24hour_distance' => json_encode(array('label' => $info['info']['skipper'], 'data' => $info['t24hour_distance'])),
                't24hour_speed'    => json_encode(array('label' => $info['info']['skipper'], 'data' => $info['t24hour_speed'])),
            );
        }
    }

    return $app['twig']->render('sail/sail.html.twig', array(
        'infos' => $infos,

        'sail1'    => $app['html']->dropdown('sail1', $app['sk'], $ids[0]),
        'sail2'    => $app['html']->dropdown('sail2', $app['sk'], isset($ids[1]) ? $ids[1] : null, '... avec'),
    ));
});

$app->get('/{_locale}', function (Request $request) use ($app) {
    return $app->redirect('/'.$request->getLocale().'/reports/latest');
});

$app->get('/', function () use ($app) {
    return $app->redirect('/en/reports/latest');
});

return $app;
