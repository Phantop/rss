<?php

use fivefilters\Readability\Readability;
use fivefilters\Readability\Configuration;
use fivefilters\Readability\ParseException;

class ReadabilityFeedExpanderBridge extends FeedExpander
{
    const NAME = 'Readability Feed Expander';
    const MAINTAINER = 'phantop';
    const URI = 'https://github.com/RSS-Bridge/rss-bridge/';
    const DESCRIPTION = 'Expand any site RSS feed using Mozilla\'s Readability';
    const PARAMETERS = [[
        'feed' => [
            'name' => 'Feed: URL of truncated RSS feed',
            'exampleValue' => 'https://example.com/feed.xml',
            'required' => true
        ],
        'replace_author' => [
            'name' => 'Replace entry authors with Readability-processed authors',
            'type' => 'checkbox',
        ],
        'replace_title' => [
            'name' => 'Replace entry titles with Readability-processed titles',
            'type' => 'checkbox',
        ],
        'limit' => self::LIMIT
    ]];

    public function collectData()
    {
        $limit = $this->getInput('limit') ?? 15;
        $this->collectExpandableDatas($this->getInput('feed'), $limit);
    }

    protected function parseItem(array $item)
    {
        $readability = new Readability(new Configuration());
        $html = getSimpleHTMLDOMCached($item['uri']);
        $html = defaultLinkTo($html, $item['uri']);
        try {
            $readability->parse($html);
            $item['content'] = $readability->getContent();
            if ($this->getInput('replace_author')) {
                $item['author'] = $readability->getAuthor();
            }
            if ($this->getInput('replace_title')) {
                $item['title'] = $readability->getTitle();
            }
        } catch (ParseException $e) {
            // Just continue on parse fails
        }

        return $item;
    }
}
