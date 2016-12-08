<?php

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

$app = require __DIR__ . '/bootstrap.php';

$app->before(function (Request $request, Application $app) {
    $app['full_screen'] = false !== $request->get('fs', false);
    $app['twig']->addGlobal('full_screen', $app['full_screen']);
});
// ---- /REDIRECT OLD URLS (indexed by search engines)
$app->get('/json/sail/{id}.kmz', function ($id) use ($app) {
    $u = $app['url_generator']->generate('sail_kmz', ['id' => $id]);

    return $app->redirect($u, 301);
});
$app->get('/json/{id}.kmz', function ($id) use ($app) {
    $u = $app['url_generator']->generate('base_kmz', ['id' => $id, 'race' => $app['race']['id']]);

    return $app->redirect($u, 301);
});
// ---- \REDIRECT OLD URLS (indexed by search engines)

$app->get('/{_locale}/map', function () use ($app) {
    return $app['twig']->render('map/map.html.twig', []);
})->bind('map');

$app->get('/{_locale}/doc/json', function () use ($app) {
    return $app['twig']->render('doc/json.html.twig', []);
})->bind('doc_json');

$app->get('/doc/json-format', function () use ($app) {
    return $app['twig']->render('doc/json-format.html.twig', []);
})->bind('doc_format');

$app->get('/{race}.kmz', function () use ($app) {
})->bind(('race_kmz'));
$app->get('/kml/{race}/sail/{id}.kmz', function ($id) use ($app) {
})->bind(('sail_kmz'));
$app->get('/kml/{race}/{id}.kmz', function () use ($app) {
})->bind(('base_kmz'));

$app->get('/json/{race}/sail/{id}.json', function ($id) use ($app) {
})->bind(('sail_json'));
$app->get('/json/{race}/FULL.json', function () use ($app) {
})->bind(('FULL_json'));
$app->get('/json/{race}/reports/{id}.json', function ($id) use ($app) {
})->bind(('reports_json'));

$app->get('/{_locale}/reports.rss', function (Request $request) use ($app) {
    $feed = $app['srv.rss']->generate();

    return new Response($feed, 200, ['Content-Type' => 'application/rss+xml']);
})->bind('reports_rss');

$app->get('/{_locale}/reports/{id}', function (Request $request, $id) use ($app) {
    $reports = $app['repo.report']->getAllBy('id', true);
    if (0 === count($reports)) {
        return new Response('No report yet', 404);
    }
    if ('latest' === $id) {
        $id = $reports[0];
    }
    if (false === $date = \DateTime::createFromFormat('Ymd-Hi', $id)) {
        if (false === preg_match("|(\d{4})(\d{2})(\d{2})-(\d{2})(\d{2})|", $id, $time)) {
            return false;
        } else {
            $ts = strtotime($time[1] . '-' . $time[2] . '-' . $time[3] . ' ' . $time[4] . ':' . $time[5]);
        }
    } else {
        $ts = $date->getTimestamp();
    }

    // --- /PAGINATION
    $idx = array_search($id, $reports, true);

    $prev = null;
    if (isset($reports[$idx + 1])) {
        $prev = $reports[$idx + 1];
    }
    $next = null;
    if (isset($reports[$idx - 1])) {
        $next = $reports[$idx - 1];
    }
    $last = reset($reports);
    $first = end($reports);
    $pagination = [
        'first' => $first,
        'prev' => $prev,
        'next' => $next,
        'last' => $last,
        'current' => abs(count($reports) - $idx),
        'total' => count($reports),
    ];
    // --- \PAGINATION

    if ('vg2012' === $app['race']['id']) {
        $report1 = $app['repo.report']->findBy(null, ['id' => $id]);
        $report2 = $app['repo.report']->findBy(null, ['has_arrived' => true, 'timestamp' => ['$lte' => $ts]]);
        $report = $report2 + $report1;
    } else {
        if (false !== $app['race']['modes']) {
            $report = $app['repo.report']->findBy(null, ['id' => $id]);
            $tmp = [];
            foreach ($report as $r) {
                $tmp[ $r['class'] ][ (string) $r['_id'] ] = $r;
            }
            $report = $tmp;
        } else {
            $report = $app['repo.report']->findBy(null, ['id' => $id]);
        }
    }

    $tpl = 'reports/reports.html.twig';
    if (false !== $app['race']['modes']) {
        $tpl = 'reports/reports_modes.html.twig';
    }

    $activeMode = false;
    if (is_array($app['race']['modes'])) {
        foreach ($app['race']['modes'] as $key => $value) {
            if (isset($report[$key])) {
                $activeMode = $key;
                break;
            }
        }
    }

    return $app['twig']->render($tpl, [
        'ts' => $ts,
        'modes' => $app['race']['modes'],
        'mode' => $request->get('mode', $activeMode), // is_array($app['race']['modes']) ? array_keys($app['race']['modes'])[0] : false),
        'report' => $report,
        'report_id' => $id,
        'start_date' => strtotime($app['race']['start_date']),
        'full' => null !== $request->get('full'),
        'pagination' => $pagination,
        'body_id' => true === $app['full_screen'] ? 'full_screen' : false,
    ]);
})->bind('report');

$app->get('/{_locale}/about', function () use ($app) {
    $reports = $app['repo.report']->getAllBy('id', true);

    return $app['twig']->render('about.html.twig', [
        'reports' => $reports,
    ]);
})->bind('about');

$app->get('/{_locale}/sail/{sailNumbers}', function ($sailNumbers) use ($app) {
    $sailNumbers = explode('-', $sailNumbers);
    $infos = [];
    foreach ($sailNumbers as $sailNumber) {
        if (false !== $info = $app['srv.vg']->getFullSailInfo($sailNumber)) {
            if (!$info['info']) {
                continue;
            }
            $info['info']['time_travelled'] = $info['info']['timestamp'] - strtotime($app['race']['start_date']);
            $info['info']['twitter'] = $app['misc']->getTwitter($info['info']['sail'], /* $noAt */ true, /* $allowAltnerative */ false);
            // $info['info']['videos'] = $app['misc']->getVideoList($info['info']['sail']);

            $c = 'rgb(' . implode(',', $app['misc']::hexToRgb($app['misc']->getColor($info['info']['sail']))) . ')';
            $infos[] = [
                'info' => $info['info'],
                'rank' => json_encode(['label' => $info['info']['skipper'], 'color' => $c, 'data' => $info['rank']]),
                'dtl' => json_encode(['label' => $info['info']['skipper'], 'color' => $c, 'data' => $info['dtl']]),
                't24hour_distance' => json_encode(['label' => $info['info']['skipper'], 'color' => $c, 'data' => $info['t24hour_distance']]),
                't24hour_speed' => json_encode(['label' => $info['info']['skipper'], 'color' => $c, 'data' => $info['t24hour_speed']]),
                'tdtl_diff' => json_encode(['label' => $info['info']['skipper'], 'color' => $c, 'data' => $info['tdtl_diff']]),
            ];
        }
    }
    if (0 === count($infos)) {
        return new Response('No report yet', 404);
    }

    return $app['twig']->render('sail/sail.html.twig', [
        'infos' => $infos,
    ]);
})->bind('sail');

$app->get('/gensitemap', function (Request $request) use ($app) {
    if (!in_array($request->getClientIp(), ['127.0.0.1', $app['config']['authIp']], true)) {
        return new Response('Not allowed', 401);
    }
    $sitemap = $app['srv.sitemap']->generate();

    return 'OK';
})->bind('sitemap');

$app->get('/{_locale}', function () use ($app) {
    return $app->redirect(
        // $app->path('report', array('id' => 'latest'))
        $app['url_generator']->generate('report', ['id' => 'latest'])
    );
})->bind('_homepage');

$app->get('/', function () use ($app) {
    return $app->redirect(
        // $app->path('report', array('id' => 'latest'))
        $app['url_generator']->generate('report', ['id' => 'latest'])
    );
})->bind('homepage');

return $app;
