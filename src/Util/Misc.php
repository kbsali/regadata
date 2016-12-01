<?php

namespace Util;
use PicoFeed\Reader\Reader;

class Misc
{
    private $skippers;

    public function __construct(array $skippers = [])
    {
        $this->skippers = $skippers;
    }

    public function getSkipper($id)
    {
        if (!isset($this->skippers[$id])) {
            return false;
        }

        return $this->skippers[$id]['skipper'];
    }

    public function getBoat($id)
    {
        if (!isset($this->skippers[$id])) {
            return false;
        }

        return $this->skippers[$id]['boat'];
    }

    public function getFeed($id)
    {
        $skipper = $this->skippers[$id];
        $ret = [
            'url' => '',
            'etag' => '',
            'last_modified' => '',
        ];
        if (isset($skipper['etag']) && !empty($skipper['etag'])) {
            $ret['etag'] = $skipper['etag'];
        }
        if (isset($skipper['last_modified']) && !empty($skipper['last_modified'])) {
            $ret['last_modified'] = $skipper['last_modified'];
        }
        if (isset($skipper['youtube']) && !empty($skipper['youtube'])) {
            $ret['url'] = sprintf('https://www.youtube.com/feeds/videos.xml?channel_id=%s', $skipper['youtube']);
        }
        if (isset($skipper['dailymotion']) && !empty($skipper['dailymotion'])) {
            $ret['url'] = sprintf('https://www.dailymotion.com/rss/user/%s/1', $skipper['dailymotion']);
        }
        return $ret;
    }

    public function getVideoList($id)
    {
        $feed = $this->getFeed($id);
        if ('' === $feed['url']) {
            return false;
        }
        $reader = new Reader;
        $resource = $reader->download($feed['url'], $feed['last_modified'], $feed['etag']);
        $parser = $reader->getParser(
            $resource->getUrl(),
            $resource->getContent(),
            $resource->getEncoding()
        );
        $feed = $parser->execute();
        echo $feed;

        // ldd($rss);
    }

    public function getTwitter($id, $noAt = false, $allowAltnerative = true)
    {
        if (!isset($this->skippers[$id])) {
            return false;
        }
        $skipper = $this->skippers[$id];
        if (isset($skipper['twitter']) && !empty($skipper['twitter'])) {
            return ($noAt ? '' : '@') . $skipper['twitter'];
        }
        if (isset($skipper['twitter_skipper1']) && !empty($skipper['twitter_skipper1'])) {
            return ($noAt ? '' : '@') . $skipper['twitter_skipper1'];
        }
        if (isset($skipper['twitter_skipper2']) && !empty($skipper['twitter_skipper2'])) {
            return ($noAt ? '' : '@') . $skipper['twitter_skipper2'];
        }
        if (isset($skipper['twitter_sponsor']) && !empty($skipper['twitter_sponsor'])) {
            return ($noAt ? '' : '@') . $skipper['twitter_sponsor'];
        }
        if (true === $allowAltnerative && isset($skipper['boat']) && !empty($skipper['boat'])) {
            return $skipper['boat'];
        }

        return false;
    }

    public function getColor($skipper)
    {
        if (!isset($this->skippers[$skipper])) {
            return false;
        }
        if (!isset($this->skippers[$skipper]['color'])) {
            return 'fff';
        }

        return $this->skippers[$skipper]['color'];
    }

    public static function sitemapPing($url, $debug = false)
    {
        $pings = [
            // 'http://submissions.ask.com/ping?sitemap=',
            'http://www.google.com/webmasters/sitemaps/ping?sitemap=',
            'http://www.bing.com/webmaster/ping.aspx?siteMap=',
        ];
        foreach ($pings as $ping) {
            $cmd = 'wget -nv -O /dev/null "' . $ping . urlencode($url) . '"';
            if ($debug) {
                echo $cmd . PHP_EOL;
            } else {
                system($cmd);
            }
        }
    }

    public static function hexToRgb($color)
    {
        $rgb = [];
        for ($x = 0; $x < 3; ++$x) {
            $rgb[$x] = hexdec(substr($color, (2 * $x), 2));
        }

        return $rgb;
    }

    public function hexToKml($color, $aa = 'ff')
    {
        $rr = substr($color, 0, 2);
        $gg = substr($color, 2, 2);
        $bb = substr($color, 4, 2);

        return strtolower($aa . $bb . $gg . $rr);
    }

    public static function kmlToRgb($color)
    {
        $rr = substr($color, 6, 2);
        $gg = substr($color, 4, 2);
        $bb = substr($color, 2, 2);

        return strtolower($rr . $gg . $bb);
    }
}
