<?php
/**
 * AnimeDb package.
 *
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
use AnimeDb\Bundle\CatalogBundle\Entity\Name;

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
        self::FIELD_STUDIO,
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
     * Browser
     *
     * @var \AnimeDb\Bundle\WorldArtFillerBundle\Service\Browser
     */
    private $browser;

    /**
     * Construct
     *
     * @param \AnimeDb\Bundle\WorldArtFillerBundle\Service\Filler $filler
     * @param \AnimeDb\Bundle\WorldArtFillerBundle\Service\Search $search
     * @param \AnimeDb\Bundle\WorldArtFillerBundle\Service\Browser $browser
     */
    public function __construct(Filler $filler, Search $search, Browser $browser)
    {
        $this->filler = $filler;
        $this->search = $search;
        $this->browser = $browser;
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
        return in_array($field, $this->supported_fields) && $this->getSourceForFill($item);
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
        if (!($url = $this->getSourceForFill($item))) {
            return $item;
        }

        if ($field == self::FIELD_IMAGES) {
            if (preg_match('/id=(?<id>\d+)/', $url, $mat)) {
                foreach ($this->filler->getFrames($mat['id']) as $frame) {
                    // check of the existence of the image
                    /* @var $image \AnimeDb\Bundle\CatalogBundle\Entity\Image */
                    foreach ($item->getImages() as $image) {
                        if ($frame == $image->getSource()) {
                            continue 2;
                        }
                    }
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
        // can refill from source. not need search
        if ($url = $this->getSourceForFill($item)) {
            return [
                new ItemRefiller(
                    $item->getName(),
                    ['url' => $url],
                    $url,
                    $item->getCover(),
                    $item->getSummary()
                )
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
            /* @var $item \AnimeDb\Bundle\CatalogBundle\Plugin\Fill\Search\Item */
            foreach ($result as $key => $item) {
                parse_str(parse_url($item->getLink(), PHP_URL_QUERY), $query);
                $link = array_values($query)[0]['url'];
                $result[$key] = new ItemRefiller(
                    $item->getName(),
                    ['url' => $link],
                    $link,
                    $item->getImage(),
                    $item->getDescription()
                );
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
            $item = $this->refill($item, $field);
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
            case self::FIELD_COUNTRY:
                $item->setCountry($new_item->getCountry());
                break;
            case self::FIELD_DATE_END:
                $item->setDateEnd($new_item->getDateEnd());
                break;
            case self::FIELD_DATE_PREMIERE:
                $item->setDatePremiere($new_item->getDatePremiere());
                break;
            case self::FIELD_DURATION:
                $item->setDuration($new_item->getDuration());
                break;
            case self::FIELD_EPISODES:
                $item->setEpisodes($new_item->getEpisodes());
                break;
            case self::FIELD_EPISODES_NUMBER:
                $item->setEpisodesNumber($new_item->getEpisodesNumber());
                break;
            case self::FIELD_FILE_INFO:
                $item->setFileInfo($new_item->getFileInfo());
                break;
            case self::FIELD_GENRES:
                /* @var $new_genre \AnimeDb\Bundle\CatalogBundle\Entity\Genre */
                foreach ($new_item->getGenres() as $new_genre) {
                    $item->addGenre($new_genre);
                }
                break;
            case self::FIELD_NAMES:
                // set main name in top of names list
                $new_names = $new_item->getNames()->toArray();
                array_unshift($new_names, (new Name())->setName($new_item->getName()));
                foreach ($new_names as $new_name) {
                    $item->addName($new_name);
                }
                break;
            case self::FIELD_SOURCES:
                foreach ($new_item->getSources() as $new_source) {
                    $item->addSource($new_source);
                }
                break;
            case self::FIELD_STUDIO:
                $item->setStudio($new_item->getStudio());
                break;
            case self::FIELD_SUMMARY:
                $item->setSummary($new_item->getSummary());
                break;
        }
        return $item;
    }

    /**
     * Get source for fill
     *
     * @param \AnimeDb\Bundle\CatalogBundle\Entity\Item $item
     *
     * @return string
     */
    public function getSourceForFill(Item $item)
    {
        /* @var $source \AnimeDb\Bundle\CatalogBundle\Entity\Source */
        foreach ($item->getSources() as $source) {
            if (strpos($source->getUrl(), $this->browser->getHost()) === 0) {
                return $source->getUrl();
            }
        }
        return '';
    }
}
