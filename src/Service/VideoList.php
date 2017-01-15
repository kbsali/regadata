<?php

namespace Service;

class VideoList
{
    public $skippers;

    public function __construct($skippers)
    {
        $this->skippers = $skippers;
    }

    public function download()
    {
        foreach ($this->skippers as $skipper) {
            if (false === $video = $this->getFeed($skipper)) {
                continue;
            }
            _ld($video);
        }
    }

    public function getFeed($skipper)
    {
        if (
            (!isset($skipper['youtube']) || empty($skipper['youtube'])) &&
            (!isset($skipper['dailymotion']) || empty($skipper['dailymotion']))
        ) {
            return false;
        }
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
    }

}
