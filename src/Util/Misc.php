<?php

namespace Util;

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

    public function getTwitter($id, $noAt = false, $allowAltnerative = true)
    {
        if (!isset($this->skippers[$id])) {
            return false;
        }
        $skipper = $this->skippers[$id];
        if (isset($this->skippers[$id]['twitter']) && !empty($this->skippers[$id]['twitter'])) {
            return ($noAt ? '' : '@') . $this->skippers[$id]['twitter'];
        }
        if (isset($this->skippers[$id]['twitter_skipper1']) && !empty($this->skippers[$id]['twitter_skipper1'])) {
            return ($noAt ? '' : '@') . $this->skippers[$id]['twitter_skipper1'];
        }
        if (isset($this->skippers[$id]['twitter_skipper2']) && !empty($this->skippers[$id]['twitter_skipper2'])) {
            return ($noAt ? '' : '@') . $this->skippers[$id]['twitter_skipper2'];
        }
        if (isset($this->skippers[$id]['twitter_sponsor']) && !empty($this->skippers[$id]['twitter_sponsor'])) {
            return ($noAt ? '' : '@') . $this->skippers[$id]['twitter_sponsor'];
        }
        if (true === $allowAltnerative && isset($this->skippers[$id]['boat']) && !empty($this->skippers[$id]['boat'])) {
            return $this->skippers[$id]['boat'];
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
