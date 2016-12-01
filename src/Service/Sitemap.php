<?php

namespace Service;

class Sitemap
{
    public $config, $race, $skippers, $urlGenerator, $_report;

    public function __construct($config, $race, $skippers, $urlGenerator, $_report)
    {
        $this->config = $config;
        $this->race = $race;
        $this->skippers = $skippers;
        $this->urlGenerator = $urlGenerator;
        $this->_report = $_report;
    }

    public function generate()
    {
        $sitemap = new \SitemapPHP\Sitemap($this->config['schema'] . $this->race['host']);

        $sitemap->setPath(__DIR__ . '/../../web/xml/');
        $sitemap->setFilename($this->race['id']);

        $reports = $this->_report->getAllBy('id', true);

        // Routes NOT requiring _locale param
        $arrNoLocle = [
            'reports_json' => ['idx' => ['k' => 'id', 'v' => $reports], 'prio' => 0.5, 'freq' => 'daily', 'race_param' => true],
            'sail_json' => ['idx' => ['k' => 'id', 'v' => array_keys($this->skippers)], 'prio' => 0.5, 'freq' => 'hourly', 'race_param' => true],
            'sail_kmz' => ['idx' => ['k' => 'id', 'v' => array_keys($this->skippers)], 'prio' => 0.8, 'freq' => 'hourly', 'race_param' => true],
            'doc_format' => ['idx' => [], 'prio' => 0.5, 'freq' => 'monthly'],
            'homepage' => ['idx' => [], 'prio' => 0.1, 'freq' => 'yearly'],
        ];
        // addItem($loc, $priority = self::DEFAULT_PRIORITY, $changefreq = NULL, $lastmod = NULL) {
        foreach ($arrNoLocle as $route => $params) {
            if (empty($params['idx'])) {
                $u = $this->urlGenerator->generate($route);
                $sitemap->addItem($u, $params['prio'], $params['freq']);
            } else {
                extract($params['idx']); // $k, $v);
                foreach ($v as $vv) {
                    $tmp = [$k => $vv];
                    if (isset($params['race_param'])) {
                        $tmp['race'] = $this->race['id'];
                    }
                    $u = $this->urlGenerator->generate($route, $tmp);
                    $sitemap->addItem($u, $params['prio'], $params['freq']);
                }
            }
        }
        // Routes REQUIRING _locale param
        $arrLocle = [
            'reports_rss' => ['idx' => [], 'prio' => 0.7, 'freq' => 'hourly'],
            'report' => ['idx' => ['k' => 'id', 'v' => $reports], 'prio' => 1, 'freq' => 'hourly'],
            'map' => ['idx' => [], 'prio' => 0.8, 'freq' => 'daily'],
            'doc_json' => ['idx' => [], 'prio' => 0.2, 'freq' => 'monthly'],
            'about' => ['idx' => [], 'prio' => 0.6, 'freq' => 'hourly'],
            'sail' => ['idx' => ['k' => 'sailNumbers', 'v' => array_keys($this->skippers)], 'prio' => 1, 'freq' => 'hourly'],
            '_homepage' => ['idx' => [], 'prio' => 0.1, 'freq' => 'yearly'],
        ];
        foreach ($arrLocle as $route => $params) {
            foreach (['en', 'fr'] as $_locale) {
                if (empty($params['idx'])) {
                    $u = $this->urlGenerator->generate($route, ['_locale' => $_locale]);
                    $sitemap->addItem($u, $params['prio'], $params['freq']);
                } else {
                    extract($params['idx']); // $k, $v);
                    foreach ($v as $vv) {
                        $u = $this->urlGenerator->generate($route, [$k => $vv, '_locale' => $_locale]);
                        $sitemap->addItem($u, $params['prio'], $params['freq']);
                    }
                }
            }
        }
        $sitemap->createSitemapIndex($this->config['schema'] . $this->race['host'] . $this->config['smDir'] . '/', 'Today');

        return $sitemap;
    }
}
