<?php

class GovTrackBridge extends BridgeAbstract
{
    const NAME = 'GovTrack';
    const MAINTAINER = 'phantop';
    const URI = 'https://www.govtrack.us/';
    const DESCRIPTION = 'Returns posts and bills from GovTrack.us';
    const PARAMETERS = [[
        'feed' => [
            'name' => 'Feed to track',
            'type' => 'list',
            'values' => [
                'All Legislative Activity' => 'bill-activity',
                'Bill Summaries' => 'bill-summaries',
                'Legislation Coming Up' => 'coming-up',
                'Major Legislative Activity' => 'major-bill-activity',
                'New Bills and Resolutions' => 'introduced-bills',
                'New Laws' => 'enacted-bills',
                ]
            ],
            'limit' => self::LIMIT
    ]];

    public function collectData()
    {
        $url = $this->getURI();
        $html = getSimpleHTMLDOMCached($url);

        $opt = [];
        preg_match('/"csrfmiddlewaretoken" value="(.*)"/', $html, $opt);
        $header = [
            "cookie: csrftoken=$opt[1]",
            "x-csrftoken: $opt[1]",
            'referer: ' . parent::getURI(),
        ];
        preg_match('/var selected_feed = "(.*)";/', $html, $opt);
        $post = [
            'count' => $this->getInput('limit') ?? 20,
            'feed' => $opt[1]
        ];
        $opt = [ CURLOPT_POSTFIELDS => $post ];

        $html = getContents(parent::getURI() . 'events/_load_events', $header, $opt);
        $html = defaultLinkTo(str_get_html($html), $url);

        foreach ($html->find('.tracked_event') as $event) {
            $item = [];

            $bill = $event->find('.event_title a, .event_body a', 0);
            $item['uri'] = $bill->href;
            $item['title'] = explode(': ', $bill->innertext)[0];
            $item['content'] = $event->find('td', 1)->innertext;

            preg_match('/Sponsor:(.*)\n/', $event->plaintext, $opt);
            $item['author'] = $opt[1] ?? '';

            $date = explode(' ', $event->find('.event_date', 0)->plaintext);
            $item['timestamp'] = strtotime(implode(' ', array_slice($date, 2)));

            foreach ($event->find('.event_title, .event_type span') as $tag) {
                if (!$tag->find('a', 0)) {
                    $item['categories'][] = $tag->plaintext;
                }
            }

            $item['enclosures'][] = $event->find('img', 0)->src;

            $this->items[] = $item;
        }
    }

    public function getName()
    {
        $name = parent::getName();
        if ($this->getInput('feed') != null) {
            $name .= ' - ' . $this->getKey('feed');
        }
        return $name;
    }

    public function getURI()
    {
        $url = parent::getURI() . 'events/' . $this->getInput('feed');
        return $url;
    }
}
