<?php

namespace Service;

class Rss
{
    public $translator, $urlGenerator, $_report;

    public function __construct($translator, $urlGenerator, $_report)
    {
        $this->translator = $translator;
        $this->urlGenerator = $urlGenerator;
        $this->_report = $_report;
    }

    public function generate()
    {
        $feed = new \Suin\RSSWriter\Feed();

        $channel = new \Suin\RSSWriter\Channel();
        $channel
               ->title($this->translator->trans('VG2012 rankings'))
               ->description($this->translator->trans('All the rankings of the Vendée Globe 2012'))
               // ->url($app->url('homepage'))
               ->url($this->urlGenerator->generate('homepage', [], true))
               ->language('en')
               ->copyright('Copyright 2012, Kevin Saliou')
               ->appendTo($feed)
           ;
        $reports = $this->_report->getAllBy('id', true);

        foreach ($reports as $reportId) {
            $ts = strtotime($reportId);
            $item = new \Suin\RSSWriter\Item();
            $item
                   ->title($this->translator->trans('General ranking %date%', ['%date%' => date('Y-m-d H:i', $ts)], 'messages', 'en'))
                   // ->description("<div>Blog body</div>")

                   // ->url($app->url('report', array('id' => $report)))
                   ->url($this->urlGenerator->generate('report', ['id' => $reportId], true))

                   // ->guid($app->url('report', array('id' => $report)), true)
                   ->guid($this->urlGenerator->generate('report', ['id' => $reportId], true), true)

                   ->pubDate($ts)
                   ->appendTo($channel)
               ;
        }

        return $feed;
    }
}
