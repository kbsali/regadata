<?php

require_once __DIR__.'/../vendor/autoload.php';

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\HttpFoundation\Request;
use Silex\Provider\TranslationServiceProvider;
use Silex\Provider\TwigServiceProvider;

$app = new Silex\Application();

$app['config'] = parse_ini_file(__DIR__.'/config.ini', TRUE);
$app['debug'] = true;

// ----- Translator
$app->register(new TranslationServiceProvider(), array(
    'locale_fallback' => 'en',
));
$app['translator'] = $app->share($app->extend('translator', function($translator, $app) {
    $translator->addLoader('yaml', new YamlFileLoader());
    $translator->addResource('yaml', __DIR__.'/locales/en.yml', 'en');
    $translator->addResource('yaml', __DIR__.'/locales/fr.yml', 'fr');

    return $translator;
}));
$app['translator.domains'] = array();

// ----- Twig
$app->register(new TwigServiceProvider(), array(
    'twig.path'    => __DIR__.'/templates',
    'twig.options' => array(
        'cache' => __DIR__.'/../cache'
    ),
));

$app->before(function(Request $request) use ($app) {

    putenv('LC_ALL='.$request->getLocale().'_'.strtoupper($request->getLocale()));
    setlocale(LC_ALL, $request->getLocale().'_'.strtoupper($request->getLocale()));
    if('fr' === $request->getLocale()) {
        $app['twig']->getExtension('core')->setDateFormat('d/m/Y à H:i');
    }

    $app['srv.vg'] = $app->share(function($app) {
        return new Service\Vg($app['config']['xlsDir'], $app['config']['docRoot'], $app['config']['jsonDir']);
    });

    $app['html'] = $app->share(function($app) {
        return new Util\HtmlHelper();
    });

    $reports     = $app['srv.vg']->listJson('reports');
    $first       = str_replace('/json', '', end($reports));
    $firstReport = $app['srv.vg']->parseJson($first);
    $sk          = $app['srv.vg']->getSailSkipper($firstReport);
    asort($sk);
    $app['sk']   = $sk;
    $app['twig']->addGlobal('sk', $app['sk']);
});

// $app->error(function (\Exception $e, $code) {
//     return new Response('We are sorry, but something went terribly wrong.');
// });

return $app;
