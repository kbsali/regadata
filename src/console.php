<?php

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

$console = new Application('VG2012', '0.1');

$console
    ->register('geo:dl')
    ->setDescription('Downloads archives from geovoile')
    ->addArgument('race', InputArgument::REQUIRED, 'Race id')
    ->setCode(function (InputInterface $input, OutputInterface $output) use ($app) {
        $app->setRace($input->getArgument('race'));

        $app['srv.geovoile']->download();
    })
;

$console
    ->register('geo:import')
    ->setDescription('Downloads archives from geovoile')
    ->addArgument('race', InputArgument::REQUIRED, 'Race id')
    ->setCode(function (InputInterface $input, OutputInterface $output) use ($app) {
        $app->setRace($input->getArgument('race'));

        $app['srv.geovoile']->parse();
    })
;

$console
    ->register('regadata:video:update')
    ->setDescription('Updates list of published videos')
    ->addArgument('race', InputArgument::REQUIRED, 'Race id')
    ->setCode(function (InputInterface $input, OutputInterface $output) use ($app) {
        $app->setRace($input->getArgument('race'));

        $app['srv.video']->download();
    })
;

$console
    ->register('regadata:dl')
    ->setDescription('Downloads the xls files from transat-bretagnemartinique.com')
    ->addArgument('race', InputArgument::REQUIRED, 'Race id')
    ->setCode(function (InputInterface $input, OutputInterface $output) use ($app) {
        $app->setRace($input->getArgument('race'));

        foreach ($app['srv.xls']->listMissingXlsx() as $f) {
            $output->writeln('<info>Downloading ' . strtr($app['race']['url_xls'], ['%file%' => $f]) . '</info>');
            file_put_contents(
                $app['srv.xls']->xlsDir . '/' . $f,
                file_get_contents(
                    strtr($app['race']['url_xls'], ['%file%' => $f])
                )
            );
        }
        $output->writeln('<info>Done</info>');
    })
;

$console
    ->register('regadata:vor:dl')
    ->setDescription('Downloads the json file from volvooceanrace.com')
    ->addArgument('race', InputArgument::REQUIRED, 'Race id')
    ->setCode(function (InputInterface $input, OutputInterface $output) use ($app) {
        $app->setRace($input->getArgument('race'));
        $app['srv.json']->downloadAndParse($input->getArgument('race'));

        $output->writeln('<info>Done</info>');
    })
;

$console
    ->register('regadata:convert')
    ->setDescription('Exports xls files to mongo')
    ->addOption('file', null, InputOption::VALUE_OPTIONAL, 'To import a specific file')
    ->addOption('force', null, InputOption::VALUE_NONE, 'Force conversion (in case document already exists)')
    ->addArgument('race', InputArgument::REQUIRED, 'Race id')
    ->setCode(function (InputInterface $input, OutputInterface $output) use ($app) {
        $app->setRace($input->getArgument('race'));

        $app['srv.xls']->xls2mongo(
            $input->getOption('file'),
            $input->getOption('force')
        );
    })
;

$console
    ->register('regadata:export')
    ->setDescription('Exports to kml + json')
    ->addOption('file', null, InputOption::VALUE_OPTIONAL, 'To import a specific file')
    ->addOption('force', null, InputOption::VALUE_NONE, 'Force conversion (in case document already exists)')
    ->addArgument('race', InputArgument::REQUIRED, 'Race id')
    ->setCode(function (InputInterface $input, OutputInterface $output) use ($app) {
        $app->setRace($input->getArgument('race'));

        $app['srv.xls']->mongo2json(
            $input->getOption('force'), 'timestamp'
        );
    })
;

$console
    ->register('regadata:ping_sitemap')
    ->addArgument('race', InputArgument::REQUIRED, 'Race id')
    ->addOption('debug', null, InputOption::VALUE_NONE, 'If set, it will NOT send the tweets')
    ->setDescription('Generates sitemaps + Ping sitemap to different search engines!')
    ->setCode(function (InputInterface $input, OutputInterface $output) use ($app) {
        $app->setRace($input->getArgument('race'));

        $cmd = 'wget -q -O /dev/null "' . $app['config']['schema'] . $app['race']['host'] . '/gensitemap"';
        $output->writeln('<info>' . $cmd . '</info>');
        system($cmd);

        $xmls = glob(__DIR__ . '/../web/xml/' . $app['race']['id'] . '*');
        foreach ($xmls as $xml) {
            if (basename($xml) === $app['race']['id'] . $app['config']['smFileName']) {
                continue;
            }
            $url = $app['config']['schema'] . $app['race']['host'] . $app['config']['smDir'] . '/' . basename($xml);
            $app['misc']::sitemapPing($url, $input->getOption('debug'));
        }
    })
;
// ---------------------------------------------------
// ----------------------- VG  -----------------------
$console
    ->register('vg:sails2mongo')
    ->setDescription('exports sails CSV to mongo')
    ->setCode(function (InputInterface $input, OutputInterface $output) use ($app) {
        $app->setRace('vg2012');

        // $s = $app['mongo']->regatta->sails;
        $sails = file(__DIR__ . '/init/sails_vg2012.csv', FILE_IGNORE_NEW_LINES);
        $header = [];
        foreach ($sails as $sail) {
            if (empty($header)) {
                $header = explode(',', $sail);
                continue;
            }
            $_sail = [];
            $_sail = explode(',', $sail);
            $app['repo.sail']->insert(array_combine($header, $_sail));
        }
        $output->writeln('ok');
    })
;

$console
    ->register('vg:rotate_icons')
    ->setDescription('Creates icons rotated from 0 to 360º')
    ->setCode(function (InputInterface $input, OutputInterface $output) use ($app) {
        $color = new Imagine\Image\Color('000', 100);
        for ($i = 0; $i <= 360; ++$i) {
            $output->writeln('<info>Generating web/icons/boat_' . $i . '.png</info>');
            $image = $app['imagine']
                ->open(__DIR__ . '/../web/img/boat_marker.png')
                ->rotate($i, $color)
                ->save(__DIR__ . '/../web/icons/boat_' . $i . '.png')
            ;
        }
    })
;

$console
    ->register('vg:tweet')
    ->addArgument('race', InputArgument::REQUIRED, 'Race id')
    ->addOption('debug', null, InputOption::VALUE_NONE, 'If set, it will NOT send the tweets')
    ->setDescription('Gets the latest report and tweet about it')
    ->setCode(function (InputInterface $input, OutputInterface $output) use ($app) {
        $app->setRace($input->getArgument('race'));

        // ---- get last report + max distance boat
        $report = $app['repo.report']->getLast();

        if (false !== $app['race']['modes']) {
            foreach ($report as $r) {
                $tmp[ $r['class'] ][ (string) $r['_id'] ] = $r;
            }
            foreach ($tmp as $class => $report) {
                $max = $app['repo.report']->extractMaxByKey($report, '24hour_distance');
                tweetIt($app, $input, $output, $max);
            }
            // $report = $tmp;
        } else {
            $max = $app['repo.report']->extractMaxByKey($report, '24hour_distance');
            tweetIt($app, $input, $output, $max);
        }
    })
;

function tweetIt($app, $input, $output, $max)
{
    if ($max['24hour_distance'] <= 0) {
        return false;
    }
    $tweet = 'Latest ranking available, fastest boat in the last 24h %skipper% (%miles% nm) %url% %hashtag%';
    $params = [
        '%hashtag%' => '#' . $app['race']['hashtag'] . (false === $app['race']['modes'] ? '' : ' #' . strtoupper($max['class'])),
        '%skipper%' => $app['misc']->getTwitter($max['sail']),
        '%miles%' => $max['24hour_distance'],
    ];

    // ---- translate tweet to french + tweet
    if (false === $app['race']['modes']) {
        $params['%url%'] = $app['race']['tweetUrlFr'];
    } else {
        $params['%url%'] = $app['race']['tweetUrlFr'][ $max['class'] ];
    }
    $_tweet = $app['translator']->trans($tweet, $params, 'messages', 'fr');
    if (isset($app['race']['showTwailorHashtag']) && true === $app['race']['showTwailorHashtag'] && strlen($_tweet) <= 131) {
        $_tweet .= ' #twailor';
    }
    $output->writeln('<info>' . $_tweet . ' (' . strlen($_tweet) . ')</info>');
    if (!$input->getOption('debug')) {
        if (strlen($_tweet) <= 140) {
            $code = $app['tmhoauth']->request('POST', $app['tmhoauth']->url('1.1/statuses/update'), [
              'status' => $_tweet,
            ]);
        }
    }

    // ---- translate tweet to english + tweet
    if (false === $app['race']['modes']) {
        $params['%url%'] = $app['race']['tweetUrlEn'];
    } else {
        $params['%url%'] = $app['race']['tweetUrlEn'][ $max['class'] ];
    }
    $_tweet = $app['translator']->trans($tweet, $params, 'en');
    if (isset($app['race']['showTwailorHashtag']) && true === $app['race']['showTwailorHashtag'] && strlen($_tweet) <= 131) {
        $_tweet .= ' #twailor';
    }
    $output->writeln('<info>' . $_tweet . ' (' . strlen($_tweet) . ')</info>');
    if (!$input->getOption('debug')) {
        if (strlen($_tweet) <= 140) {
            $code = $app['tmhoauth']->request('POST', $app['tmhoauth']->url('1.1/statuses/update'), [
              'status' => $_tweet,
            ]);
        }
    }
}

return $console;
