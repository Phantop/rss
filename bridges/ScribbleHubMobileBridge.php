<?php

class ScribbleHubMobileBridge extends FeedExpander
{
    const MAINTAINER = 'phantop';
    const NAME = 'ScribbleHub';
    const URI = 'https://www.scribblehub.com/';
    const DESCRIPTION = 'Returns chapters from Scribble Hub (Mobile API).';
    const PARAMETERS = [
        'Author' => [
            'uid' => [
                'name' => 'User ID',
                'required' => true,
                // Example: miriamrobern's stories
                'exampleValue' => '149271',
            ],
        ],
        'Series' => [
            'sid' => [
                'name' => 'Series ID',
                'required' => true,
                // Example: latest chapters from Uskweirs
                'exampleValue' => '965299',
            ],
        ],
        'Search' => [
            'search' => [
                'name' => 'Search Query',
            ],
            'genres' => [
                'name' => 'Genres',
            ],
            'tags' => [
                'name' => 'tags',
            ],
            'sort' => [
                'name' => 'Sort by',
                'type' => 'list',
                'values' => [
                    'Date Added' => 'new',
                    'Last Update' => 'updated',
                    'Number of Ratings' => 'numratings',
                    'Pageviews' => 'pageviews',
                    'Rating' => 'rating',
                    'Readers' => 'readers',
                ]
            ],
            'limit' => self::LIMIT,
        ],
    ];
    const CONFIGURATION = [
        'username' => [
            'required' => false,
        ],
        'password' => [
            'required' => false,
        ],
    ];


    protected $author = '';
    protected $slug = '';
    protected $title = '';

    const FEEDURI = 'https://rssscribblehub.com/rssfeed.php?type=author&uid=';
    public function collectData()
    {
        if ($this->queriedContext === 'Author') {
            $this->collectExpandableDatas(self::FEEDURI . $this->getInput('uid'));
        }
        if ($this->queriedContext === 'Series') {
            $this->collectSeries($this->getInput('sid'));
        }
        if ($this->queriedContext === 'Search') {
            $this->collectList();
        }
    }

    private function getApi($route, $headers = [], $opts = [])
    {
        $opts += [
            CURLOPT_USERAGENT => 'ScribbleHub/1.0.9 (ios; build 28)',
        ];

        $api = self::URI . "wp-json/fictionapp/v1/$route";
        $content = getContents($api, $headers, $opts);
        return json_decode($content, true)['data'];
    }

    protected function parseItem(array $item)
    {
        $cid = basename($item['uri']);
        try {
            $api = $this->getApi("chapters/$cid");
            $story = $api['story'];
        } catch (HttpException $e) {
            // Feed can return removed chapters, which will 404
            if ($e->getCode() === 404) {
                return $item;
            }
            throw $e;
        }

        $item = [
            'author' => $story['author']['displayName'],
            'comments' => $item['uri'] . '#comments',
            'content' => $api['content'],
            'timestamp' => strtotime($api['publishedAt']),
            'title' => $story['title'] . ': ' . $api['title'],
            'uid' => $cid,
            'uri' => $item['uri'],
        ];

        //Retrieve image for thumbnail
        $item_image = $story['coverUrl'];
        if ($item_image) {
            $item['enclosures'] = [$item_image];
        }

        $this->author = $item['author'];
        $this->title = $story['title'];
        $sid = $story['id'];

        //Restore lost categories
        $item['categories'] = [$this->author, $this->title, $sid];

        return $item;
    }

    private function collectSeries($sid, $limit = 9999)
    {
        $api = $this->getApi("stories/$sid");
        $this->slug = $api['slug'];
        $api = $this->getApi("stories/$sid/chapters?perPage=$limit");
        foreach ($api as $chapter) {
            $cid = $chapter['id'];
            $item = [
                'uri' => self::URI . "read/$this->slug/chapter/$cid",
            ];

            $item = $this->parseItem($item);
            $this->items[] = $item;
        }
    }

    private function collectList()
    {
        $genres = explode(',', $this->getInput('genres'));
        $genresInclude = array_filter($genres, fn($s) => !str_starts_with($s, '-'));
        $genresExclude = array_diff($genres, $genresInclude);

        $tags = explode(',', $this->getInput('tags'));
        $tagsInclude = array_filter($tags, fn($s) => !str_starts_with($s, '-'));
        $tagsExclude = array_diff($tags, $tagsInclude);

        $params = [
            'genreMatch' => 'all',
            'genres' => implode(',', $genresInclude),
            'genresExclude' => implode(',', $genresExclude),
            'perPage' => $this->getInput('limit'),
            'search' => $this->getInput('search'),
            'sort' => $this->getInput('sort'),
            'tagMatch' => 'all',
            'tags' => implode(',', $tagsInclude),
            'tagsExclude' => implode(',', $tagsExclude),

        ];

        $api = $this->getApi('stories?' . http_build_query($params), $this->getAuth());
        foreach ($api as $element) {
            $sid = $element['id'];
            $this->collectSeries($sid, 1);
            $item = array_pop($this->items);

            $chapter_content = $item['content'];
            $chapter_title = $item['title'];
            $chapter_uri = $item['uri'];

            $item['title'] = $element['title'];
            $item['uri'] = self::URI . "series/$sid/$this->slug";
            $item['content'] = $element['description'];
            $item['content'] .= "<hr><h3><a href=\"$chapter_uri\">$chapter_title</a></h3>$chapter_content";

            foreach ($element['genres'] as $genre) {
                $item['categories'][] = $genre['name'];
            }
            foreach ($element['tags'] as $tag) {
                $item['categories'][] = $tag['name'];
            }

            $this->items[] = $item;
        }
    }

    private function getAuth()
    {
        $opts = [
            CURLOPT_POSTFIELDS => [
                'username' => $this->getOption('username'),
                'password' => $this->getOption('password'),
            ]
        ];
        try {
            $api = $this->getApi('auth/login', [], $opts);
            return ['Authorization: Bearer ' . $api['accessToken']];
        } catch (HttpException $e) {
            // Expect 400 with no or bad login credentials
            if ($e->getCode() === 400) {
                return [];
            }
            throw $e;
        }
    }

    public function getIcon()
    {
        return self::URI . 'favicon.ico';
    }

    public function getName()
    {
        $name = parent::getName() . " $this->queriedContext";
        switch ($this->queriedContext) {
            case 'Author':
                $title = $this->author;
                break;
            case 'Series':
                $title = $this->title;
                break;
        }
        if (isset($title)) {
            $name .= " - $title";
        }
        return $name;
    }

    public function getURI()
    {
        $uri = parent::getURI();
        switch ($this->queriedContext) {
            case 'Author':
                $uri = self::URI . 'profile/' . $this->getInput('uid');
                break;
            case 'Series':
                $uri = self::URI . 'series/' . $this->getInput('sid') . "/$this->slug";
                break;
        }
        return $uri;
    }
}
