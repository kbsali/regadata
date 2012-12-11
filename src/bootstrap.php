<?php

require_once __DIR__.'/../vendor/autoload.php';

$app = new Silex\Application();

$app['config'] = parse_ini_file(__DIR__.'/config.ini', TRUE);
$app['debug'] = true;

// ----- Twig
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path'    => __DIR__.'/templates',
    'twig.options' => array('cache' => __DIR__.'/../cache'),
));

// ----- Translator
use Symfony\Component\Translation\Loader\YamlFileLoader;
$app->register(new Silex\Provider\TranslationServiceProvider(), array(
    'locale_fallback' => 'en',
));
$app['translator'] = $app->share($app->extend('translator', function($translator, $app) {
    $translator->addLoader('yaml', new YamlFileLoader());
    $translator->addResource('yaml', __DIR__.'/locales/en.yml', 'en');
    $translator->addResource('yaml', __DIR__.'/locales/fr.yml', 'fr');

    return $translator;
}));

$app->before(function() use ($app) {

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

    $app['lSail'] = $app['srv.vg']->listJson('sail');
    $app['_lSail'] = array_map(function($s) {
        return str_replace(array('/json', '.json'), '', $s);
    }, $app['lSail']);
});

return $app;
