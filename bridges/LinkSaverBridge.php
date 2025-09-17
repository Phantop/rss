<?php

class LinkSaverBridge extends BridgeAbstract
{
    const MAINTAINER = 'phantop';
    const NAME = 'Link Saver';
    const URI = 'https://github.com/RSS-Bridge/rss-bridge/';
    const DESCRIPTION = 'Make a temporary feed with custom links to save in your feed reader';
    const PARAMETERS = [[
        'key' => [
            'name' => 'Link save key',
            'type' => 'text',
            'required' => true,
        ],
        'url' => [
            'name' => 'URL to save',
            'type' => 'text',
            'required' => false,
        ],
        'fetch' => [
            'name' => 'Fetch information from pages',
            'type' => 'checkbox',
            'required' => false,
            'defaultValue' => 'unchecked',
        ],
    ]];

    // Save for 12 hours to best account for feed reader refresh times being long
    const SAVE_TIMEOUT = 12 * 60 * 60;

    public function collectData()
    {
        $key = $this->getInput('key');
        $data = $this->loadCacheValue($key) ?? [];
        if ($this->getInput('url')) {
            $data[$this->getInput('url')] = time();
            $this->saveCacheValue($key, $data, self::SAVE_TIMEOUT);
        }
        foreach ($data as $url => $time) {
            $item = [
                'timestamp' => $time,
                'title' => $url,
                'uri' => $url,
            ];

            if ($this->getInput('fetch')) {
                $html = getSimpleHTMLDOMCached($url);
                $item['title'] = $html->find('head title', 0)->plaintext;
                $item['content'] = $html->find('body', 0);
            }

            $this->items[] = $item;
        }
    }
}
