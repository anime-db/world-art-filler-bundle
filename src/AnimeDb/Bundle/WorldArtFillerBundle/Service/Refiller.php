<?php
/**
 * AnimeDb package
 *
 * @package   AnimeDb
 * @author    Peter Gribanov <info@peter-gribanov.ru>
 * @copyright Copyright (c) 2011, Peter Gribanov
 * @license   http://opensource.org/licenses/GPL-3.0 GPL v3
 */

namespace AnimeDb\Bundle\WorldArtFillerBundle\Service;

use AnimeDb\Bundle\CatalogBundle\Plugin\Fill\Refiller\Refiller as RefillerPlugin;
use AnimeDb\Bundle\CatalogBundle\Plugin\Fill\Refiller\Item as ItemRefiller;
use AnimeDb\Bundle\CatalogBundle\Entity\Item;
use AnimeDb\Bundle\CatalogBundle\Entity\Source;
use AnimeDb\Bundle\CatalogBundle\Entity\Image;

/**
 * Refiller from site world-art.ru
 * 
 * @link http://world-art.ru/
 * @package AnimeDb\Bundle\WorldArtFillerBundle\Service
 * @author  Peter Gribanov <info@peter-gribanov.ru>
 */
class Refiller extends RefillerPlugin
{
    /**
     * Name
     *
     * @var string
     */
    const NAME = 'world-art';

    /**
     * Title
     *
     * @var string
     */
    const TITLE = 'World-Art.ru';

    /**
     * Filler http host
     *
     * @var string
     */
    const HOST = 'http://www.world-art.ru/';

    /**
     * List of supported fields
     *
     * @var array
     */
    protected $supported_fields = [
        self::FIELD_DATE_END,
        self::FIELD_DATE_PREMIERE,
        self::FIELD_DURATION,
        self::FIELD_EPISODES,
        self::FIELD_EPISODES_NUMBER,
        self::FIELD_GENRES,
        self::FIELD_IMAGES,
        self::FIELD_COUNTRY,
        self::FIELD_NAMES,
        self::FIELD_SOURCES,
        self::FIELD_SUMMARY
    ];

    /**
     * Filler
     *
     * @var \AnimeDb\Bundle\WorldArtFillerBundle\Service\Filler
     */
    protected $filler;

    /**
     * Search
     *
     * @var \AnimeDb\Bundle\WorldArtFillerBundle\Service\Search
     */
    protected $search;

    /**
     * Construct
     *
     * @param \AnimeDb\Bundle\WorldArtFillerBundle\Service\Filler $filler
     * @param \AnimeDb\Bundle\WorldArtFillerBundle\Service\Search $search
     */
    public function __construct(Filler $filler, Search $search)
    {
        $this->filler = $filler;
        $this->search = $search;
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName() {
        return self::NAME;
    }

    /**
     * Get title
     *
     * @return string
     */
    public function getTitle() {
        return self::TITLE;
    }

    /**
     * Is can refill item from source
     *
     * @param \AnimeDb\Bundle\CatalogBundle\Entity\Item $item
     * @param string $field
     *
     * @return boolean
     */
    public function isCanRefill(Item $item, $field)
    {
        if (!in_array($field, $this->supported_fields)) {
            return false;
        }
        /* @var $source \AnimeDb\Bundle\CatalogBundle\Entity\Source */
        foreach ($item->getSources() as $source) {
            if (strpos($source->getUrl(), self::HOST) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Refill item field from source
     *
     * @param \AnimeDb\Bundle\CatalogBundle\Entity\Item $item
     * @param string $field
     *
     * @return \AnimeDb\Bundle\CatalogBundle\Entity\Item
     */
    public function refill(Item $item, $field)
    {
        // get source url
        $url = '';
        foreach ($item->getSources() as $source) {
            if (strpos($source->getUrl(), self::HOST) === 0) {
                $url = $source->getUrl();
                break;
            }
        }
        if (!$url) {
            return $item;
        }

        if ($field == self::FIELD_IMAGES) {
            if (preg_match('/id=(?<id>\d+)/', $url, $mat)) {
                foreach ($this->filler->getFrames($mat['id']) as $frame) {
                    $item->addImage((new Image())->setSource($frame));
                }
            }
        } elseif ($new_item = $this->filler->fill(['url' => $url, 'frames' => false])) {
            $item = $this->fillItem($item, $new_item, $field);
        }
        return $item;
    }

    /**
     * Is can search
     *
     * @param \AnimeDb\Bundle\CatalogBundle\Entity\Item $item
     * @param string $field
     *
     * @return boolean
     */
    public function isCanSearch(Item $item, $field)
    {
        if (!in_array($field, $this->supported_fields)) {
            return false;
        }
        if ($this->isCanRefill($item, $field) || $item->getName()) {
            return true;
        }
        /* @var $name \AnimeDb\Bundle\CatalogBundle\Entity\Name */
        foreach ($item->getNames() as $name) {
            if ($name->getName()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Search items for refill
     *
     * @param \AnimeDb\Bundle\CatalogBundle\Entity\Item $item
     * @param string $field
     *
     * @return array [\AnimeDb\Bundle\CatalogBundle\Plugin\Fill\Refiller\Item]
     */
    public function search(Item $item, $field)
    {
        // search source url
        $url = '';
        foreach ($item->getSources() as $source) {
            if (strpos($source->getUrl(), self::HOST) === 0) {
                $url = $source->getUrl();
                break;
            }
        }
        // can refill from source. not need search
        if ($url) {
            return [
                new ItemRefiller($item->getName(), ['url' => $url], $item->getCoverWebPath(), $item->getSummary())
            ];
        }

        // get name for search
        if (!($name = $item->getName())) {
            foreach ($item->getNames() as $name) {
                if ($name) {
                    break;
                }
            }
        }

        $result = [];
        // do search
        if ($name) {
            $result = $this->search->search(['name' => $name]);
            $empty_link = $this->search->getLinkForFill('');
            /* @var $item \AnimeDb\Bundle\CatalogBundle\Plugin\Fill\Search\Item */
            foreach ($result as $key => $item) {
                $link = urldecode(str_replace($empty_link, '', $item->getLink()));
                $result[$key] = new ItemRefiller($item->getName(), ['url' => $link], $link);
            }
        }

        return $result;
    }

    /**
     * Refill item field from search result
     *
     * @param \AnimeDb\Bundle\CatalogBundle\Entity\Item $item
     * @param string $field
     * @param array $data
     *
     * @return \AnimeDb\Bundle\CatalogBundle\Entity\Item
     */
    public function refillFromSearchResult(Item $item, $field, array $data)
    {
        if (!empty($data['url'])) {
            $source = new Source();
            $source->setUrl($data['url']);
            $item->addSource($source);
            $new_item = $this->refill($item, $field);
            $item = $this->fillItem($item, $new_item, $field);
        }
        return $item;
    }

    /**
     * Fill item
     *
     * @param \AnimeDb\Bundle\CatalogBundle\Entity\Item $item
     * @param \AnimeDb\Bundle\CatalogBundle\Entity\Item $new_item
     * @param string $field
     *
     * @return \AnimeDb\Bundle\CatalogBundle\Entity\Item
     */
    protected function fillItem(Item $item, Item $new_item, $field)
    {
        switch ($field) {
            case self::FIELD_SUMMARY:
                $item->setSummary($new_item->getSummary());
                break;
            case self::FIELD_EPISODES:
                $item->setEpisodes($new_item->getEpisodes());
                break;
            case self::FIELD_GENRES:
                /* @var $new_genre \AnimeDb\Bundle\CatalogBundle\Entity\Genre */
                foreach ($new_item->getGenres() as $new_genre) {
                    // check of the existence of the genre
                    /* @var $genre \AnimeDb\Bundle\CatalogBundle\Entity\Genre */
                    foreach ($item->getGenres() as $genre) {
                        if ($new_genre->getName() == $genre->getName()) {
                            continue 2;
                        }
                    }
                    $item->addGenre($new_genre);
                }
                break;
            case self::FIELD_NAMES:
                /* @var $new_name \AnimeDb\Bundle\CatalogBundle\Entity\Name */
                foreach ($new_item->getNames() as $new_name) {
                    // check of the existence of the name
                    /* @var $name \AnimeDb\Bundle\CatalogBundle\Entity\Name */
                    foreach ($item->getNames() as $name) {
                        if ($new_name->getName() == $name->getName()) {
                            continue 2;
                        }
                    }
                    $item->addName($new_name);
                }
                break;
        }
        return $item;
    }
}