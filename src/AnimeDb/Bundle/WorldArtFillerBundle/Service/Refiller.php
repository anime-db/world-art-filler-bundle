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
use AnimeDb\Bundle\CatalogBundle\Entity\Item;

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
    public function isCanRefillFromSource(Item $item, $field)
    {
        return true;
    }

    /**
     * Refill item field from source
     *
     * @param \AnimeDb\Bundle\CatalogBundle\Entity\Item $item
     * @param string $field
     *
     * @return \AnimeDb\Bundle\CatalogBundle\Entity\Item
     */
    public function refillFromSource(Item $item, $field)
    {
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
        return true;
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
        return [];
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
        return $item;
    }
}