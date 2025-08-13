<?php
class GGDealsBridge extends BridgeAbstract {

    const DESCRIPTION = 'Returns the price history for a game from gg.deals.';
    const MAINTAINER = 'phantop';
    const NAME = 'GG.deals';
    const URI = 'https://gg.deals/';

    const PARAMETERS = [[
        'slug' => [
            'name' => 'Game slug',
            'type' => 'text',
            'required' => true,
            'title' => 'Game slug from the gg.deals URL',
            'exampleValue' => 'a-hat-in-time-ultimate-edition-nintendo-switch'
        ],
        'region' => [
            'name' => 'Region',
            'type' => 'list',
            'required' => true,
            'title' => 'Select the region for pricing',
            'defaultValue' => 'us',
            'values' => [
                'Australia' => 'au',
                'Belgium' => 'be',
                'Brazil' => 'br',
                'Canada' => 'ca',
                'Denmark' => 'dk',
                'Europe' => 'eu',
                'Finland' => 'fi',
                'France' => 'fr',
                'Germany' => 'de',
                'Ireland' => 'ie',
                'Italy' => 'it',
                'Netherlands' => 'nl',
                'Norway' => 'no',
                'Poland' => 'pl',
                'Spain' => 'es',
                'Sweden' => 'se',
                'Switzerland' => 'ch',
                'United Kingdom' => 'gb',
                'United States' => 'us',
            ],
        ],
        'keyshops' => [
            'name' => 'Include keyshops',
            'type' => 'checkbox',
            'title' => 'Check to include prices from keyshops',
            'defaultValue' => 'checked'
        ],
        'lowest' => [
            'name' => 'Only return lowest prices',
            'type' => 'checkbox',
            'title' => 'Check to only show a price if it\'s the new lowest',
            'defaultValue' => 'checked'
        ]
    ]];

    public function collectData() {
        $html = getSimpleHTMLDOMCached($this->getURI());
        $id_attr = 'data-container-game-id';
        $url = sprintf(
            '%s%s/games/chartHistoricalData/%s/?showKeyshops=%d',
            self::URI,
            $this->getInput('region'),
            $html->find("[$id_attr]", 0)->getAttribute("$id_attr"),
            $this->getInput('keyshops')
        );

        $headers = [ 'X-Requested-With: XMLHttpRequest' ];
        $json = getContents($url, $headers);
        $data = json_decode($json);

        $currency = $data->currency;
        $types = (array)($data->chartData);

        foreach ($types as $type => $deals) {
            $low = array_pop($deals);
            foreach ($deals as $deal) {
                $item = [];

                $name = $deal->name;
                $shop = $deal->shop;
                $price = number_format($deal->y, $currency->decimals);
                $timestamp = intval($deal->x / 1000);

                $item['author'] = $shop;
                $item['categories'] = [$type];
                $item['timestamp'] = $timestamp;
                $item['title'] = "$shop: $currency->prefix$price$currency->suffix";
                $item['uid'] = "$deal->shop$deal->x$deal->y";
                $item['uri'] = $this->getURI();

                if ($deal->y == $low->y) {
                    $item['title'] .= " ($type low)";
                } elseif ($this->getInput('lowest')) {
                    continue;
                }

                $this->items[] = $item;
            }
        }

        usort($this->items, function($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });
    }

    public function getName() {
        $name = parent::getName();
        if ($this->getInput('slug')) {
            $html = getSimpleHTMLDOMCached($this->getURI());
            $name .= ' - ' . end($html->find('[itemscope] span'))->innertext;
        }
        return $name;
    }

    public function getURI() {
        if ($this->getInput('slug')) {
            return self::URI . 'game/' . $this->getInput('slug');
        }

        return parent::getURI();
    }
}
