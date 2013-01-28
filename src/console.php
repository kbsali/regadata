<?php

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

$console = new Application('VG2021', '0.1');

$console
    ->register('vg:dl')
    ->setDescription('Downloads the xls files from vendeeglobe.org')
    ->setCode(function (InputInterface $input, OutputInterface $output) use ($app) {

        foreach ($app['srv.vgxls']->listMissingXlsx() as $f) {
            $output->writeln('<info>Downloading '.$f.'</info>');
            file_put_contents($app['srv.vgxls']->xlsDir.'/'.$f, file_get_contents('http://tracking2012.vendeeglobe.org/download/'.$f));
        }
        $output->writeln('<info>Done</info>');
    })
;

$console
    ->register('vg:convert')
    ->setDescription('Converts the xls files to json')
    ->addOption('file', null, InputOption::VALUE_OPTIONAL, 'To import a specific file')
    ->setCode(function (InputInterface $input, OutputInterface $output) use ($app) {
        $app['srv.vgxls']->xls2json($input->getOption('file'));
    })
;

$console
    ->register('vg:ping_sitemap')
    ->setDescription('Generates sitemaps + Ping sitemap to different search engines!')
    ->setCode(function (InputInterface $input, OutputInterface $output) use ($app) {

        $cmd = 'wget -q -O /dev/null "'.$app['config']['schema'].$app['config']['host'].'/gensitemap"';
        $output->writeln('<info>'.$cmd.'</info>');
        system($cmd);

        $xmls = glob(__DIR__.'/../web/xml/*');
        foreach ($xmls as $xml) {
            if (basename($xml) === $app['config']['smFileName']) {
                continue;
            }
            $url = $app['config']['schema'].$app['config']['host'].$app['config']['smDir'].'/'.basename($xml);
            $app['misc']::sitemapPing($url);
        }
    })
;

$console
    ->register('vg:rotate_icons')
    ->setDescription('Creates icons rotated from 0 to 360º')
    ->setCode(function (InputInterface $input, OutputInterface $output) use ($app) {
        $color = new Imagine\Image\Color('000', 100);
        for($i=0; $i<=360; $i++) {
            $output->writeln('<info>Generating web/icons/boat_'.$i.'.png</info>');
            $image = $app['imagine']
                ->open(__DIR__.'/../web/img/boat_marker.png')
                ->rotate($i, $color)
                ->save(__DIR__.'/../web/icons/boat_'.$i.'.png')
            ;
        }
    })
;

$console
    ->register('vg:tweet')
    ->setDescription('Gets the latest report and tweet about it')
    ->setCode(function (InputInterface $input, OutputInterface $output) use ($app) {

        $reports = $app['srv.vg']->listJson('reports');
        $id      = str_replace(array('/json/reports/', '.json'), '', $reports[0]);
        $report  = $app['srv.vg']->parseJson('/reports/'.$id.'.json');
        $max     = $app['srv.vg']->extractMaxByKey($report, '24hour_distance');
        $tweet   = '#vg2012 Latest ranking available, fastest skipper in the last 24h %skipper% (%miles% nm) %url%';

        $params = array(
            '%skipper%' => $app['srv.vg']->sailToTwitter($max['sail']),
            '%miles%'   => $max['24hour_distance'],
        );

        // in french
        // $params['%url%'] = 'http://vg2012.saliou.name/fr/reports/latest';
        $params['%url%'] = 'goo.gl/B8yKv';
        $_tweet = $app['translator']->trans($tweet, $params, 'messages', 'fr');
        if(strlen($_tweet) > 140) {
            /*
            http://tinyurl.com/vg2012fr
            http://goo.gl/AQyJL
            goo.gl/B8yKv (includes UTM_SOURCE ...)
             */
            $params['%url%'] = 'http://goo.gl/AQyJL';
            $_tweet = $app['translator']->trans($tweet, $params, 'messages', 'fr');
        }
        $output->writeln('<info>'.$_tweet.' ('.strlen($_tweet).')</info>');
        if(strlen($_tweet) <= 140) {
            $code = $app['tmhoauth']->request('POST', $app['tmhoauth']->url('1/statuses/update'), array(
              'status' => $_tweet
            ));
        }

        // in english
        // $params['%url%'] = 'http://vg2012.saliou.name/en/reports/latest';
        $params['%url%'] = 'goo.gl/3VJyD';
        $_tweet = $app['translator']->trans($tweet, $params);
        if(strlen($_tweet) > 140) {
            /*
            http://tinyurl.com/vg2012en
            http://myurl.in/vg2012en
            http://yep.it/vg2012en
            http://goo.gl/YwGgM
            goo.gl/3VJyD (includes UTM_SOURCE ...)
             */
            $params['%url%'] = 'http://goo.gl/YwGgM';
            $_tweet = $app['translator']->trans($tweet, $params);
        }
        $output->writeln('<info>'.$_tweet.' ('.strlen($_tweet).')</info>');
        if(strlen($_tweet) <= 140) {
            $code = $app['tmhoauth']->request('POST', $app['tmhoauth']->url('1/statuses/update'), array(
              'status' => $_tweet
            ));
        }
    })
;

return $console;