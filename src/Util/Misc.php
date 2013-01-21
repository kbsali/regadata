<?php

namespace Util;

class Misc
{
    public static function sitemapPing($url)
    {
        $pings = array(
            'http://submissions.ask.com/ping?sitemap=',
            'http://www.google.com/webmasters/sitemaps/ping?sitemap=',
            'http://www.bing.com/webmaster/ping.aspx?siteMap='
        );
        foreach ($pings as $ping) {
            $cmd = 'wget -nv -O /dev/null "'.$ping.urlencode($url).'"';
            echo $cmd.PHP_EOL;
            // system($cmd);
        }

    }
}