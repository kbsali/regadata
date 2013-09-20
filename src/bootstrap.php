<?php

require_once __DIR__.'/../vendor/autoload.php';


use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\HttpFoundation\Request;

use Silex\Provider\HttpCacheServiceProvider;
use Silex\Provider\TranslationServiceProvider;
use Silex\Provider\TwigServiceProvider;

function ldd($a) {
    var_export($a);die(PHP_EOL.'---------------------'.PHP_EOL);
}
function ld($a) {
    var_export($a);echo PHP_EOL.'---------------------'.PHP_EOL;
}

class MyApp extends Silex\Application
{
    public static function extractSubDomain($request) {
        $tmp = explode('.', $request->getHost());
        if (3 !== count($tmp)) {
            return false;
        }
        return $tmp[0];
    }

    public function setRace($raceId = null) {
        if(null === $raceId) {
            $raceId = getenv('RACE') ?: 'vg2012';
        }

        if(!isset($this['races'][$raceId])) {
            throw new \Exception('Race not defined');
            // die('Race no defined');
        }
        $this['repo.report']->setRace($raceId);
        $this['repo.sail']->setRace($raceId);
        $this['race'] = $this['races'][$raceId];

        $this['sk'] = $this['repo.sail']->findBy('sail');
        $this['misc'] = $this->share(function($this) {
            return new Util\Misc(
                $this['sk']
            );
        });

        $this['translator'] = $this->share($this->extend('translator', function($translator, $this) {
            $translator->addLoader('yaml', new YamlFileLoader());
            $translator->addResource('yaml', __DIR__.'/locales/'.$this['race']['id'].'_en.yml', 'en');
            $translator->addResource('yaml', __DIR__.'/locales/'.$this['race']['id'].'_fr.yml', 'fr');

            return $translator;
        }));
        $this['translator.domains'] = array();
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
require __DIR__.'/config.php';
require __DIR__.'/races.php';

$app['tmhoauth.config'] = array(
    'consumer_key'    => $app['config']['consumer_key'],
    'consumer_secret' => $app['config']['consumer_secret'],
    'user_token'      => $app['config']['user_token'],
    'user_secret'     => $app['config']['user_secret'],
);

// --- Providers
$app->register(new HttpCacheServiceProvider());
$app->register(new TranslationServiceProvider());

$app->register(new TwigServiceProvider(), array(
    'twig.path'    => __DIR__.'/templates',
    'twig.options' => array(
        'cache'            => isset($app['twig.options.cache']) ? $app['twig.options.cache'] : false,
        'strict_variables' => true
    ),
));

$app->register(new Service\Provider\TmhOAuthServiceProvider());
$app->register(new Silex\Provider\UrlGeneratorServiceProvider());

if(!isset($app['imagine.factory'])) {
    $app['imagine.factory'] = 'Imagick';
}

$app['imagine'] = $app->share(function ($app) {
    $class = sprintf('\Imagine\%s\Imagine', $app['imagine.factory']);
    return new $class();
});

$app['mongo'] = $app->share(function($app) {
   return new \MongoClient('mongodb://127.0.0.1:27017');
});

$app['repo.report'] = $app->share(function($app) {
    return new Repository\Report($app['mongo']);
});
$app['repo.sail'] = $app->share(function($app) {
    return new Repository\Sail($app['mongo']);
});

$app['srv.rss'] = $app->share(function($app) {
    return new Service\Rss(
        $app['translator'],
        $app['url_generator'],
        $app['repo.report']
    );
});

$app['misc'] = new stdclass();

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
        $app['config']['kmlDir'],
        $app['repo.report'],
        $app['misc'],
        $app['race'],
        $app['repo.sail']
    );
});
$app['srv.tbmxls'] = $app->share(function($app) {
    return new Service\TbmXls(
        $app['config']['xlsDir'],
        $app['config']['jsonDir'],
        $app['config']['kmlDir'],
        $app['repo.report'],
        $app['misc'],
        $app['race'],
        $app['repo.sail']
    );
});

$app['gvparser'] = $app->share(function($app) {
    return new Service\GeovoileParser(
        $app['repo.report']
    );
});

$app['srv.geovoile'] = $app->share(function($app) {
    return new Service\Geovoile(
        $app['config']['xmlDir'],
        $app['config']['jsonDir'],
        $app['gvparser'],
        $app['repo.report'],
        $app['misc'],
        $app['race']
    );
});
// --- Before
$app->before(function(Request $request) use ($app) {

    $app->setRace();

    if(!in_array($request->getLocale(), array('en', 'fr'))) {
        $u = $app['url_generator']->generate('_homepage', array('_locale' => 'en'));
        return $app->redirect($u);
    }

    putenv('LC_ALL='.$request->getLocale().'_'.strtoupper($request->getLocale()));
    setlocale(LC_ALL, $request->getLocale().'_'.strtoupper($request->getLocale()));
    if('fr' === $request->getLocale()) {
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
