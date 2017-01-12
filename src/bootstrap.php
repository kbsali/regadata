<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Silex\Provider\HttpCacheServiceProvider;
use Silex\Provider\TranslationServiceProvider;
use Silex\Provider\TwigServiceProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Service\VideoList;

function _ldd($a)
{
    var_export($a);
    die(PHP_EOL . '---------------------' . PHP_EOL);
}
function _ld($a)
{
    var_export($a);
    echo PHP_EOL . '---------------------' . PHP_EOL;
}

class MyApp extends Silex\Application
{
    public static function extractSubDomain($request)
    {
        $tmp = explode('.', $request->getHost());
        if (3 !== count($tmp)) {
            return false;
        }

        return $tmp[0];
    }

    public function setRace($raceId = null)
    {
        if (null === $raceId) {
            $raceId = getenv('RACE') ?: 'vg2012';
        }

        if (!isset($this['races'][$raceId])) {
            throw new \Exception('Race not defined');
            // die('Race no defined');
        }
        $this['race'] = $this['races'][$raceId];
        $this['repo.report']->setRace($raceId);
        $this['repo.sail']->setRace(isset($this['race']['subid']) ? $this['race']['subid'] : $raceId);

        $this['sk'] = $this['repo.sail']->findBy('sail');
        $this['misc'] = $this->share(function ($app) {
            return new Util\Misc(
                $app['sk']
            );
        });

        $this['translator'] = $this->share($this->extend('translator', function ($translator, $app) {
            $translator->addLoader('yaml', new YamlFileLoader());
            $translator->addResource('yaml', __DIR__ . '/locales/' . (isset($app['race']['subid']) ? $app['race']['subid'] : $app['race']['id']) . '_en.yml', 'en');
            $translator->addResource('yaml', __DIR__ . '/locales/' . (isset($app['race']['subid']) ? $app['race']['subid'] : $app['race']['id']) . '_fr.yml', 'fr');

            return $translator;
        }));
        $this['translator.domains'] = [];
    }
    // use Silex\Application\TwigTrait;
    // use Silex\Application\SecurityTrait;
    // use Silex\Application\FormTrait;
    // use Silex\Application\SwiftmailerTrait;
    // use Silex\Application\MonologTrait;
    // use Silex\Application\UrlGeneratorTrait;
    // use Silex\Application\TranslationTrait;
}
$app = new MyApp();
require __DIR__ . '/config.php';
require __DIR__ . '/races.php';

$app['tmhoauth.config'] = [
    'consumer_key' => $app['config']['consumer_key'],
    'consumer_secret' => $app['config']['consumer_secret'],
    'user_token' => $app['config']['user_token'],
    'user_secret' => $app['config']['user_secret'],
];

// --- Providers
$app->register(new HttpCacheServiceProvider());
$app->register(new TranslationServiceProvider());

$app->register(new TwigServiceProvider(), [
    'twig.path' => __DIR__ . '/templates',
    'twig.options' => [
        'cache' => isset($app['twig.options.cache']) ? $app['twig.options.cache'] : false,
        'strict_variables' => true,
    ],
]);

$app->register(new Service\Provider\TmhOAuthServiceProvider());
$app->register(new Silex\Provider\UrlGeneratorServiceProvider());

if (!isset($app['imagine.factory'])) {
    $app['imagine.factory'] = 'Imagick';
}

$app['imagine'] = $app->share(function ($app) {
    $class = sprintf('\Imagine\%s\Imagine', $app['imagine.factory']);

    return new $class();
});

$app['mongo'] = $app->share(function ($app) {
    return new \MongoDB\Client('mongodb://' . $app['config']['mongoHost'] . ':' . $app['config']['mongoPort']);
});

$app['repo.report'] = $app->share(function ($app) {
    return new Repository\Report($app['mongo']);
});
$app['repo.sail'] = $app->share(function ($app) {
    return new Repository\Sail($app['mongo']);
});

$app['srv.sitemap'] = $app->share(function ($app) {
    return new Service\Sitemap(
        $app['config'],
        $app['race'],
        $app['sk'],
        $app['url_generator'],
        $app['repo.report']
    );
});

$app['srv.rss'] = $app->share(function ($app) {
    return new Service\Rss(
        $app['translator'],
        $app['url_generator'],
        $app['repo.report']
    );
});

$app['misc'] = new stdclass();

$app['srv.vg'] = $app->share(function ($app) {
    return new Service\Vg(
        $app['config']['xlsDir'],
        $app['config']['docRoot'],
        $app['config']['jsonDir'],
        $app['repo.report']
    );
});

$app['srv.json'] = $app->share(function ($app) {
    return new $app['race']['xls_service_class'](
        $app['config']['xlsDir'],
        $app['config']['jsonDir'],
        $app['config']['geoJsonDir'],
        $app['config']['kmlDir'],
        $app['repo.report'],
        $app['misc'],
        $app['race'],
        $app['repo.sail']
    );
});

$app['srv.xls'] = $app->share(function ($app) {
    return new $app['race']['xls_service_class'](
        $app['config']['xlsDir'],
        $app['config']['jsonDir'],
        $app['config']['geoJsonDir'],
        $app['config']['kmlDir'],
        $app['repo.report'],
        $app['misc'],
        $app['race'],
        $app['repo.sail']
    );
});

$app['gvparser'] = $app->share(function ($app) {
    return new Service\GeovoileParser(
        $app['repo.report']
    );
});

$app['srv.geovoile'] = $app->share(function ($app) {
    return new Service\Geovoile(
        $app['config']['xmlDir'],
        $app['config']['jsonDir'],
        $app['gvparser'],
        $app['repo.report'],
        $app['misc'],
        $app['race']
    );
});

$app['srv.video'] = $app->share(function ($app) {
    return new \Service\VideoList(
        $app['sk']
    );
});
// --- Before
$app->before(function (Request $request) use ($app) {
    $app->setRace();

    if (!in_array($request->getLocale(), ['en', 'fr'], true)) {
        $u = $app['url_generator']->generate('_homepage', ['_locale' => 'en']);

        return $app->redirect($u);
    }

    putenv('LC_ALL=' . $request->getLocale() . '_' . strtoupper($request->getLocale()));
    setlocale(LC_ALL, $request->getLocale() . '_' . strtoupper($request->getLocale()));
    if ('fr' === $request->getLocale()) {
        $app['twig']->getExtension('core')->setDateFormat('d/m/Y à H:i');
    }
    $app['twig']->addGlobal('sk', $app['sk']);
    $app['twig']->addGlobal('race', $app['race']);
    $app['twig']->addGlobal('debug', $app['debug']);
    $app['twig']->addGlobal('assets_local', $app['assets.local']);
});

// $app->error(function (\Exception $e, $code) {
//     return new Response('We are sorry, but something went terribly wrong.');
// });

return $app;
