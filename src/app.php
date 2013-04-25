<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

$app = require __DIR__.'/bootstrap.php';

// ---- /REDIRECT OLD URLS (indexed by search engines)
$app->get('/json/sail/{id}.kmz', function ($id) use ($app) {
    $u = $app['url_generator']->generate('sail_kmz', array('id' => $id));
    return $app->redirect($u, 301);
});
$app->get('/json/{id}.kmz', function ($id) use ($app) {
    $u = $app['url_generator']->generate('base_kmz', array('id' => $id, 'race' => $app['race']['id']));
    return $app->redirect($u, 301);
});
// ---- \REDIRECT OLD URLS (indexed by search engines)

$app->get('/{_locale}/map', function () use ($app) {
    return $app['twig']->render('map/map.html.twig', array());
})->bind('map');

$app->get('/{_locale}/doc/json', function () use ($app) {
    return $app['twig']->render('doc/json.html.twig', array());
})->bind('doc_json');

$app->get('/doc/json-format', function () use ($app) {
    return $app['twig']->render('doc/json-format.html.twig', array());
})->bind('doc_format');


$app->get('/{race}.kmz', function () use ($app) {})->bind(('race_kmz'));
$app->get('/kml/{race}/sail/{id}.kmz', function ($id) use ($app) {})->bind(('sail_kmz'));
$app->get('/kml/{race}/{id}.kmz', function () use ($app) {})->bind(('base_kmz'));

$app->get('/json/{race}/sail/{id}.json', function ($id) use ($app) {})->bind(('sail_json'));
$app->get('/json/{race}/FULL.json', function () use ($app) {})->bind(('FULL_json'));
$app->get('/json/{race}/reports/{id}.json', function ($id) use ($app) {})->bind(('reports_json'));


$app->get('/{_locale}/reports.rss', function (Request $request) use ($app) {
    $feed = new Suin\RSSWriter\Feed();

    $channel = new Suin\RSSWriter\Channel();
    $channel
        ->title($app['translator']->trans('VG2012 rankings'))
        ->description($app['translator']->trans('All the rankings of the Vendée Globe 2012'))
        // ->url($app->url('homepage'))
        ->url($app['url_generator']->generate('homepage', array(), true))
        ->language('en')
        ->copyright('Copyright 2012, Kevin Saliou')
        ->appendTo($feed)
    ;
    $reports = $app['repo.report']->getAllBy('id', true);

    foreach ($reports as $reportId) {
        $ts = strtotime($reportId);
        $item = new Suin\RSSWriter\Item();
        $item
            ->title($app['translator']->trans('General ranking %date%', array('%date%' => date('Y-m-d H:i', $ts)), 'messages', 'en'))
            // ->description("<div>Blog body</div>")

            // ->url($app->url('report', array('id' => $report)))
            ->url($app['url_generator']->generate('report', array('id' => $reportId), true))

            // ->guid($app->url('report', array('id' => $report)), true)
            ->guid($app['url_generator']->generate('report', array('id' => $reportId), true), true)

            ->pubDate($ts)
            ->appendTo($channel)
        ;
    }

    return new Response($feed, 200, array('Content-Type' => 'application/rss+xml'));
})->bind('reports_rss');

$app->get('/{_locale}/reports/{id}', function (Request $request, $id) use ($app) {
    $reports = $app['repo.report']->getAllBy('id', true);

    if ('latest' === $id) {
        $id = $reports[0];
    }
    if (false === preg_match("|(\d{4})(\d{2})(\d{2})-(\d{2})(\d{2})|", $id, $time)) {
        return false;
    }
    $ts = strtotime($time[1].'-'.$time[2].'-'.$time[3].' '.$time[4].':'.$time[5]);

    // --- /PAGINATION
    $idx = array_search($id, $reports);

    $prev = null;
    if (isset($reports[$idx + 1])) {
        $prev = $reports[$idx + 1];
    }
    $next = null;
    if (isset($reports[$idx - 1])) {
        $next = $reports[$idx - 1];
    }
    $last  = reset($reports);
    $first = end($reports);
    $pagination = array(
        'first'   => $first,
        'prev'    => $prev,
        'next'    => $next,
        'last'    => $last,
        'current' => abs(count($reports) - $idx),
        'total'   => count($reports),
    );
    // --- \PAGINATION

    $report1 = $app['repo.report']->findBy(null, array('id' => $id));
    $report2 = $app['repo.report']->findBy(null, array('has_arrived' => true, 'timestamp' => array('$lte' => $ts)));
    $report = $report2+$report1;

    return $app['twig']->render('reports/reports.html.twig', array(
        'ts'         => $ts,
        'report'     => $report,
        'report_id'  => $id,
        'start_date' => strtotime($app['race']['start_date']),
        'full'       => null !== $request->get('full'),
        'pagination' => $pagination,
    ));
})->bind('report');

$app->get('/{_locale}/about', function () use ($app) {
    $reports = $app['repo.report']->getAllBy('id', true);

    return $app['twig']->render('about.html.twig', array(
        'reports' => $reports,
    ));
})->bind('about');

$app->get('/{_locale}/sail/{ids}', function ($ids) use ($app) {
    $ids   = explode('-', $ids);
    $infos = array();

    foreach ($ids as $id) {
        if (false !== $info = $app['srv.vg']->getFullSailInfo($id)) {
            if(!$info['info']) {
                continue;
            }
            $info['info']['time_travelled'] = $info['info']['timestamp'] - strtotime($app['race']['start_date']);
            $info['info']['twitter'] = $app['misc']->getTwitter($info['info']['sail'], true);

            $c = 'rgb('.join(',', $app['misc']::hexToRgb($app['misc']->getColor($info['info']['sail']))).')';
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

$app->get('/gensitemap', function (Request $request) use ($app) {
    if(!in_array($request->getClientIp(), array('127.0.0.1', $app['config']['authIp']))) {
        return new Response('Not allowed', 401);
    }
    $sitemap = new SitemapPHP\Sitemap($app['config']['schema'].$app['race']['host']);
    $sitemap->setPath(__DIR__.'/../web/xml/');
    $sitemap->setFilename($app['race']['id']);

    $reports = $app['repo.report']->getAllBy('id', true);

    // Routes NOT requiring _locale param
    $arrNoLocle = array(
        'reports_json' => array('idx' => array('k' => 'id', 'v' => $reports), 'prio' => 0.5, 'freq' => 'daily', 'race_param' => true),
        'sail_json'    => array('idx' => array('k' => 'id', 'v' => array_keys($app['sk'])), 'prio' => 0.5, 'freq' => 'hourly'),
        'sail_kmz'     => array('idx' => array('k' => 'id', 'v' => array_keys($app['sk'])), 'prio' => 0.8, 'freq' => 'hourly'),
        'doc_format'   => array('idx' => array(), 'prio' => 0.5, 'freq' => 'monthly'),
        'homepage'     => array('idx' => array(), 'prio' => 0.1, 'freq' => 'yearly'),
    );
    // addItem($loc, $priority = self::DEFAULT_PRIORITY, $changefreq = NULL, $lastmod = NULL) {
    foreach($arrNoLocle as $route => $params) {
        if(empty($params['idx'])) {
            $u = $app['url_generator']->generate($route);
            $sitemap->addItem($u, $params['prio'], $params['freq']);
        } else {
            extract($params['idx']); // $k, $v);
            foreach($v as $vv) {
                $tmp = array($k => $vv);
                if(isset($params['race_param'])) {
                    $tmp['race'] = $app['race']['id'];
                }
                $u = $app['url_generator']->generate($route, $tmp);
                $sitemap->addItem($u, $params['prio'], $params['freq']);
            }
        }
    }
    // Routes REQUIRING _locale param
    $arrLocle = array(
        'reports_rss' => array('idx' => array(), 'prio' => 0.7, 'freq' => 'hourly'),
        'report'      => array('idx' => array('k' => 'id', 'v' => $reports), 'prio' => 1, 'freq' => 'hourly'),
        'map'         => array('idx' => array(), 'prio' => 0.8, 'freq' => 'daily'),
        'doc_json'    => array('idx' => array(), 'prio' => 0.2, 'freq' => 'monthly'),
        'about'       => array('idx' => array(), 'prio' => 0.6, 'freq' => 'hourly'),
        'sail'        => array('idx' => array('k' => 'ids', 'v' => array_keys($app['sk'])), 'prio' => 1, 'freq' => 'hourly'),
        '_homepage'   => array('idx' => array(), 'prio' => 0.1, 'freq' => 'yearly'),
    );
    foreach($arrLocle as $route => $params) {
        foreach(array('en', 'fr') as $_locale) {
            if(empty($params['idx'])) {
                $u = $app['url_generator']->generate($route, array('_locale' => $_locale));
                $sitemap->addItem($u, $params['prio'], $params['freq']);
            } else {
                extract($params['idx']); // $k, $v);
                foreach($v as $vv) {
                    $u = $app['url_generator']->generate($route, array($k => $vv, '_locale' => $_locale));
                   $sitemap->addItem($u, $params['prio'], $params['freq']);
                }
            }
        }
    }

    $sitemap->createSitemapIndex($app['config']['schema'].$app['race']['host'].$app['config']['smDir'].'/', 'Today');
    return 'OK';

})->bind('sitemap');

$app->get('/{_locale}', function () use ($app) {
    return $app->redirect(
        // $app->path('report', array('id' => 'latest'))
        $app['url_generator']->generate('report', array('id' => 'latest'))
    );
})->bind('_homepage');

$app->get('/', function () use ($app) {
    return $app->redirect(
        // $app->path('report', array('id' => 'latest'))
        $app['url_generator']->generate('report', array('id' => 'latest'))
    );
})->bind('homepage');

return $app;