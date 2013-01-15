<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

$app = require __DIR__.'/bootstrap.php';

// REDIRECT OLD URLS (indexed by search engines)
$app->get('/reports/{id}', function (Request $request) use ($app) {
    return $app->redirect('/'.$request->getLocale().'/reports/'.$request->get('id'));
});
$app->get('/sail/{id}', function (Request $request) use ($app) {
    return $app->redirect('/'.$request->getLocale().'/sail/'.$request->get('id'));
});

$app->get('/{_locale}/reports.rss', function (Request $request) use ($app) {

    $feed = new Suin\RSSWriter\Feed();

    $channel = new Suin\RSSWriter\Channel();
    $channel
        ->title($app->trans('VG2012 rankings'))
        ->description($app->trans('All the rankings of the Vendée Globe 2012'))
        ->url($app->url('homepage'))
        ->language('en')
        ->copyright('Copyright 2012, Kevin Saliou')
        ->appendTo($feed)
    ;
    $reports = $app['srv.vg']->getReportsById(
        $app['srv.vg']->listJson('reports')
    );
    foreach ($reports as $report => $ts) {
        $item = new Suin\RSSWriter\Item();
        $item
            ->title($app->trans('General ranking %date%', array('%date%' => date('Y-m-d H:i', $ts)), 'messages', 'en'))
            // ->description("<div>Blog body</div>")
            ->url($app->url('report', array('id' => $report)))
            ->guid($app->url('report', array('id' => $report)), true)
            ->pubDate($ts)
            ->appendTo($channel)
        ;
    }

    return new Response($feed, 200, array('Content-Type' => 'application/rss+xml'));
})->bind('reports_rss');

$app->get('/json/reports/{id}.json', function ($id) use ($app) {})->bind(('reports_json'));
$app->get('/json/sail/{id}.json', function ($id) use ($app) {})->bind(('sail_json'));
$app->get('/json/sail/{id}.kmz', function ($id) use ($app) {})->bind(('sail_kmz'));

$app->get('/{_locale}/reports/{id}', function (Request $request, $id) use ($app) {
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
    $last  = str_replace(array('/json', '.json'), '', $reports[0]);
    $first = str_replace(array('/json', '.json'), '', end($reports));

    $report = $app['srv.vg']->parseJson('/reports/'.$id.'.json');

    return $app['twig']->render('reports/reports.html.twig', array(
        'r'          => current($report),
        'report'     => $report,
        'source'     => '/json/reports/'.$id.'.json',
        'start_date' => strtotime($app['config']['start_date']),
        'full'       => null !== $request->get('full'),

        'pagination' => array(
            'first'   => $first,
            'prev'    => $prev,
            'next'    => $next,
            'last'    => $last,
            'current' => abs(count($reports) - $idx),
            'total'   => count($reports),
        )
    ));
})->bind('report');

$app->get('/{_locale}/map', function () use ($app) {
    return $app['twig']->render('map/map.html.twig', array());
})->bind('map');

$app->get('/{_locale}/doc/json', function () use ($app) {
    return $app['twig']->render('doc/json.html.twig', array());
})->bind('doc_json');

$app->get('/doc/json-format', function () use ($app) {
    return $app['twig']->render('doc/json-format.html.twig', array());
})->bind('doc_format');

$app->get('/{_locale}/about', function () use ($app) {
    $reports     = $app['srv.vg']->listJson('reports');
    $first       = str_replace('/json', '', end($reports));
    $firstReport = $app['srv.vg']->parseJson($first);

    return $app['twig']->render('about.html.twig', array(
        'rndSail' => array_rand($app['sk']),
        'reports' => $app['srv.vg']->getReportsById($reports),
    ));
})->bind('about');

$app->get('/{_locale}/sail/{ids}', function ($ids) use ($app) {
    $ids   = explode('-', $ids);
    $infos = array();

    foreach ($ids as $id) {
        if (false !== $info = $app['srv.vg']->getFullSailInfo($id)) {
            $info['info']['time_travelled'] = $info['info']['timestamp'] - strtotime($app['config']['start_date']);

            $c = 'rgb('.join(',',$app['srv.vgxls']::hexToRgb($app['srv.vg']::sailToColor($info['info']['sail']))).')';
            $infos[] = array(
                'info'             => $info['info'],
                'rank'             => json_encode(array('label' => $info['info']['skipper'], 'color' => $c, 'data' => $info['rank'])),
                'dtl'              => json_encode(array('label' => $info['info']['skipper'], 'color' => $c, 'data' => $info['dtl'])),
                't24hour_distance' => json_encode(array('label' => $info['info']['skipper'], 'color' => $c, 'data' => $info['t24hour_distance'])),
                't24hour_speed'    => json_encode(array('label' => $info['info']['skipper'], 'color' => $c, 'data' => $info['t24hour_speed'])),
                'tdtl_diff'        => json_encode(array('label' => $info['info']['skipper'], 'color' => $c, 'data' => $info['tdtl_diff'])),
            );
        }
    }
    return $app['twig']->render('sail/sail.html.twig', array(
        'infos' => $infos,
    ));
})->bind('sail');

$app->get('/{_locale}', function () use ($app) {
    return $app->redirect(
        $app->path('report', array('id' => 'latest'))
    );
})->bind('_homepage');

$app->get('/', function () use ($app) {
    return $app->redirect(
        $app->path('report', array('id' => 'latest'))
    );
})->bind('homepage');

return $app;