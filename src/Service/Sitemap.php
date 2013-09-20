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
        $sitemap = new \SitemapPHP\Sitemap($this->config['schema'].$this->race['host']);

        $sitemap->setPath(__DIR__.'/../../web/xml/');
        $sitemap->setFilename($this->race['id']);

        $reports = $this->_report->getAllBy('id', true);

        // Routes NOT requiring _locale param
        $arrNoLocle = array(
            'reports_json' => array('idx' => array('k' => 'id', 'v' => $reports), 'prio' => 0.5, 'freq' => 'daily', 'race_param' => true),
            'sail_json'    => array('idx' => array('k' => 'id', 'v' => array_keys($this->skippers)), 'prio' => 0.5, 'freq' => 'hourly', 'race_param' => true),
            'sail_kmz'     => array('idx' => array('k' => 'id', 'v' => array_keys($this->skippers)), 'prio' => 0.8, 'freq' => 'hourly', 'race_param' => true),
            'doc_format'   => array('idx' => array(), 'prio' => 0.5, 'freq' => 'monthly'),
            'homepage'     => array('idx' => array(), 'prio' => 0.1, 'freq' => 'yearly'),
        );
        // addItem($loc, $priority = self::DEFAULT_PRIORITY, $changefreq = NULL, $lastmod = NULL) {
        foreach ($arrNoLocle as $route => $params) {
            if (empty($params['idx'])) {
                $u = $this->urlGenerator->generate($route);
                $sitemap->addItem($u, $params['prio'], $params['freq']);
            } else {
                extract($params['idx']); // $k, $v);
                foreach ($v as $vv) {
                    $tmp = array($k => $vv);
                    if (isset($params['race_param'])) {
                        $tmp['race'] = $this->race['id'];
                    }
                    $u = $this->urlGenerator->generate($route, $tmp);
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
            'sail'        => array('idx' => array('k' => 'ids', 'v' => array_keys($this->skippers)), 'prio' => 1, 'freq' => 'hourly'),
            '_homepage'   => array('idx' => array(), 'prio' => 0.1, 'freq' => 'yearly'),
        );
        foreach ($arrLocle as $route => $params) {
            foreach (array('en', 'fr') as $_locale) {
                if (empty($params['idx'])) {
                    $u = $this->urlGenerator->generate($route, array('_locale' => $_locale));
                    $sitemap->addItem($u, $params['prio'], $params['freq']);
                } else {
                    extract($params['idx']); // $k, $v);
                    foreach ($v as $vv) {
                        $u = $this->urlGenerator->generate($route, array($k => $vv, '_locale' => $_locale));
                       $sitemap->addItem($u, $params['prio'], $params['freq']);
                    }
                }
            }
        }
        $sitemap->createSitemapIndex($this->config['schema'].$this->race['host'].$this->config['smDir'].'/', 'Today');

        return $sitemap;
    }
}