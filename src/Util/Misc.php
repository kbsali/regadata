<?php

namespace Util;

class Misc
{
    public static function sitemapPing($url, $debug = false)
    {
        $pings = array(
            // 'http://submissions.ask.com/ping?sitemap=',
            'http://www.google.com/webmasters/sitemaps/ping?sitemap=',
            'http://www.bing.com/webmaster/ping.aspx?siteMap='
        );
        foreach ($pings as $ping) {
            $cmd = 'wget -nv -O /dev/null "'.$ping.urlencode($url).'"';
            if($debug) {
                echo $cmd.PHP_EOL;
            } else {
                system($cmd);
            }
        }
    }

    public static function hexToRgb($color)
    {
        $rgb = array();
        for ($x=0;$x<3;$x++) {
            $rgb[$x] = hexdec(substr($color, (2*$x), 2));
        }
        return $rgb;
    }
}