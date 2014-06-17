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

use AnimeDb\Bundle\CatalogBundle\Plugin\Fill\Search\Search as SearchPlugin;
use AnimeDb\Bundle\CatalogBundle\Plugin\Fill\Search\Item as ItemSearch;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use AnimeDb\Bundle\WorldArtFillerBundle\Form\Search as SearchForm;
use Knp\Menu\ItemInterface;

/**
 * Search from site world-art.ru
 * 
 * @link http://world-art.ru/
 * @package AnimeDb\Bundle\WorldArtFillerBundle\Service
 * @author  Peter Gribanov <info@peter-gribanov.ru>
 */
class Search extends SearchPlugin
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
     * Path for search
     *
     * @var string
     */
    const SEARH_URL = '/search.php?public_search=#NAME#&global_sector=#SECTOR#';

    /**
     * XPath for list search items
     *
     * @var string
     */
    const XPATH_FOR_LIST = '//center/table/tr/td/table/tr/td/table/tr/td';

    /**
     * Default sector
     *
     * @var string
     */
    const DEFAULT_SECTOR = 'all';

    /**
     * Browser
     *
     * @var \AnimeDb\Bundle\WorldArtFillerBundle\Service\Browser
     */
    private $browser;

    /**
     * Construct
     *
     * @param \AnimeDb\Bundle\WorldArtFillerBundle\Service\Browser $browser
     */
    public function __construct(Browser $browser) {
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
     * Build menu for plugin
     *
     * @param \Knp\Menu\ItemInterface $item
     *
     * @return \Knp\Menu\ItemInterface
     */
    public function buildMenu(ItemInterface $item)
    {
        return parent::buildMenu($item)
            ->setLinkAttribute('class', 'icon-label icon-label-plugin-world-art');
    }

    /**
     * Search source by name
     *
     * Return structure
     * <code>
     * [
     *     \AnimeDb\Bundle\CatalogBundle\Plugin\Fill\Search\Item
     * ]
     * </code>
     *
     * @param array $data
     *
     * @return array
     */
    public function search(array $data)
    {
        $name = iconv('utf-8', 'cp1251', $data['name']);
        $url = str_replace('#NAME#', urlencode($name), self::SEARH_URL);
        $url = str_replace('#SECTOR#', (isset($data['type']) ? $data['type'] : self::DEFAULT_SECTOR), $url);
        // get list from xpath
        $dom = $this->browser->getDom($url);
        $xpath = new \DOMXPath($dom);

        // if for request is found only one result is produced forwarding
        $refresh = $xpath->query('//meta[@http-equiv="Refresh"]/@content');
        if ($refresh->length) {
            list(, $url) = explode('url=', $refresh->item(0)->nodeValue, 2);
            // add http if need
            if ($url[0] == '/') {
                $url = $this->browser->getHost().$url;
            }
            $name = iconv('cp1251', 'utf-8', $name);
            if (!preg_match('/id=(?<id>\d+)/', $url, $mat) || !($type = $this->filler->getItemType($url))) {
                throw new NotFoundHttpException('Incorrect URL for found item');
            }
            return [
                new ItemSearch(
                    $name,
                    $this->getLinkForFill($url),
                    $this->filler->getCoverUrl($mat['id'], $type),
                    ''
                )
            ];
        }

        $rows = $xpath->query(self::XPATH_FOR_LIST);

        $list = [];
        foreach ($rows as $el) {
            $link = $xpath->query('a', $el);
            // has link on source
            if ($link->length &&
                ($href = $link->item(0)->getAttribute('href')) &&
                ($name = $link->item(0)->nodeValue) &&
                preg_match('/id=(?<id>\d+)/', $href, $mat) &&
                ($type = $this->filler->getItemType($href))
            ) {
                $list[] = new ItemSearch(
                    str_replace(["\r\n", "\n"], ' ', $name),
                    $this->getLinkForFill($this->browser->getHost().'/'.$href),
                    $this->filler->getCoverUrl($mat['id'], $type),
                    trim(str_replace($name, '', $el->nodeValue)),
                    $this->browser->getHost().'/'.$href
                );
            }
        }

        return $list;
    }

    /**
     * Get form
     *
     * @return \AnimeDb\Bundle\WorldArtFillerBundle\Form\Search
     */
    public function getForm()
    {
        return new SearchForm();
    }
}