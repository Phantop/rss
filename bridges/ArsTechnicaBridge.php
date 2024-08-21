<?php

class ArsTechnicaBridge extends FeedExpander
{
    const MAINTAINER = 'phantop';
    const NAME = 'Ars Technica';
    const URI = 'https://arstechnica.com/';
    const DESCRIPTION = 'Returns the latest articles from Ars Technica';
    const PARAMETERS = [[
        'section' => [
            'name' => 'Site section',
            'type' => 'list',
            'defaultValue' => 'index',
            'values' => [
                'All' => 'index',
                'Apple' => 'apple',
                'Board Games' => 'cardboard',
                'Cars' => 'cars',
                'Features' => 'features',
                'Gaming' => 'gaming',
                'Information Technology' => 'technology-lab',
                'Science' => 'science',
                'Staff Blogs' => 'staff-blogs',
                'Tech Policy' => 'tech-policy',
                'Tech' => 'gadgets',
            ]
        ]
    ]];

    public function collectData()
    {
        $url = 'https://feeds.arstechnica.com/arstechnica/' . $this->getInput('section');
        $this->collectExpandableDatas($url, 10);
    }

    protected function parseItem(array $item)
    {
        $item['uid'] = explode('=', $item['uri'])[1];

        $item_html = getSimpleHTMLDOMCached($item['uri'] . '&comments=1');
        $item_html = defaultLinkTo($item_html, self::URI);
        $item['content'] = $item_html->find('.article-content', 0);
        $item['comments'] = $item_html->find('#view_comments', 0)->href;

        $pages = $item_html->find('nav.page-numbers > .numbers > a', -2);
        if ($pages) {
            for ($i = 2; $i <= $pages->innertext; $i++) {
                $page_url = $item['uri'] . '&page=' . $i;
                $page_html = getSimpleHTMLDOMCached($page_url);
                $page_html = defaultLinkTo($page_html, self::URI);
                $item['content'] .= $page_html->find('.article-content', 0);
            }
            $item['content'] = str_get_html($item['content']);
        }

        // remove various ars advertising
        $sel = '#social-left, .ars-component-buy-box, .ad_wrapper, .sidebar';
        foreach ($item['content']->find($sel) as $ad) {
            $ad->remove();
        }

        $item['content'] = backgroundToImg($item['content']);

        $parsely = $item_html->find('[name="parsely-page"]', 0)->content;
        $parsely_json = json_decode(html_entity_decode($parsely), true);
        $item['categories'] = $parsely_json['tags'];

        return $item;
    }
}
