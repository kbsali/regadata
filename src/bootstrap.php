<?php

require_once __DIR__.'/../vendor/autoload.php';


use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\HttpFoundation\Request;

use Silex\Provider\HttpCacheServiceProvider;
use Silex\Provider\TranslationServiceProvider;
use Silex\Provider\TwigServiceProvider;

use Kud\Silex\Provider\TmhOAuthServiceProvider;

function ldd($a) {
    var_export($a);die;
}
function ld($a) {
    var_export($a);
}

class MyApp extends Silex\Application
{
    // use Silex\Application\TwigTrait;
    // use Silex\Application\SecurityTrait;
    // use Silex\Application\FormTrait;
    // use Silex\Application\SwiftmailerTrait;
    // use Silex\Application\MonologTrait;

    // use Silex\Application\UrlGeneratorTrait;
    // use Silex\Application\TranslationTrait;
}

$app = new MyApp();

require __DIR__.'/config.php';

$app['tmhoauth.config'] = array(
    'consumer_key'    => $app['config']['consumer_key'],
    'consumer_secret' => $app['config']['consumer_secret'],
    'user_token'      => $app['config']['user_token'],
    'user_secret'     => $app['config']['user_secret'],
);

// --- Providers
$app->register(new HttpCacheServiceProvider());
$app->register(new TranslationServiceProvider());
$app['translator'] = $app->share($app->extend('translator', function($translator, $app) {
    $translator->addLoader('yaml', new YamlFileLoader());
    $translator->addResource('yaml', __DIR__.'/locales/en.yml', 'en');
    $translator->addResource('yaml', __DIR__.'/locales/fr.yml', 'fr');

    return $translator;
}));
$app['translator.domains'] = array();

$app->register(new TwigServiceProvider(), array(
    'twig.path'    => __DIR__.'/templates',
    'twig.options' => array(
        'cache'            => isset($app['twig.options.cache']) ? $app['twig.options.cache'] : false,
        'strict_variables' => true
    ),
));

$app->register(new TmhOAuthServiceProvider());
$app->register(new Silex\Provider\UrlGeneratorServiceProvider());

if(!isset($app['imagine.factory'])) {
    $app['imagine.factory'] = 'Imagick';
}

$app['imagine'] = $app->share(function ($app) {
    $class = sprintf('\Imagine\%s\Imagine', $app['imagine.factory']);
    return new $class();
});

$app['misc'] = $app->share(function($app) {
    return new Util\Misc();
});

$app['mongo'] = $app->share(function($app) {
    return new \MongoClient();
});

$app['repo.report'] = $app->share(function($app) {
    return new Repository\Report($app['mongo']);
});

$app['srv.vg'] = $app->share(function($app) {
    return new Service\Vg(
        $app['config']['xlsDir'],
        $app['config']['docRoot'],
        $app['config']['jsonDir'],
        $app['repo.report']
    );
});

$app['srv.vgxls'] = $app->share(function($app) {
    return new Service\VgXls(
        $app['config']['xlsDir'],
        $app['config']['jsonDir'],
        $app['repo.report'],
        $app['race']['arrival_lat'],
        $app['race']['arrival_lon'],
        $app['race']['arrival']
    );
});

// --- Before
$app->before(function(Request $request) use ($app) {

    putenv('LC_ALL='.$request->getLocale().'_'.strtoupper($request->getLocale()));
    setlocale(LC_ALL, $request->getLocale().'_'.strtoupper($request->getLocale()));
    if('fr' === $request->getLocale()) {
        $app['twig']->getExtension('core')->setDateFormat('d/m/Y à H:i');
    }
    /*
    $app['html'] = $app->share(function($app) {
        return new Util\HtmlHelper();
    });
    */

    $reports     = $app['srv.vg']->listJson('reports');
    $first       = str_replace('/json', '', end($reports));
    $firstReport = $app['srv.vg']->parseJson($first);
    $app['sk']   = $app['srv.vg']->getSailSkipper($firstReport);

    $app['twig']->addGlobal('sk', $app['sk']);
    $app['twig']->addGlobal('debug', $app['debug']);
    $app['twig']->addGlobal('assets_local', $app['assets.local']);
});

// $app->error(function (\Exception $e, $code) {
//     return new Response('We are sorry, but something went terribly wrong.');
// });

return $app;
