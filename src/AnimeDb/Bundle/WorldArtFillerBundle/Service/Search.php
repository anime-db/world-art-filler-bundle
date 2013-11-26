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
     * Filler http host
     *
     * @var string
     */
    const HOST = 'http://www.world-art.ru/';

    /**
     * Path for search
     *
     * @var string
     */
    const SEARH_URL = 'search.php?public_search=#NAME#&global_sector=animation';

    /**
     * XPath for list search items
     *
     * @var string
     */
    const XPATH_FOR_LIST = '//center/table/tr/td/table/tr/td/table/tr/td';

    /**
     * Default HTTP User-Agent
     *
     * @var string
     */
    const DEFAULT_USER_AGENT = 'Mozilla/5.0 (Windows NT 6.2; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/29.0.1547.2 Safari/537.36';

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
        // get list from xpath
        $dom = $this->browser->getDom(self::HOST.$url);
        $xpath = new \DOMXPath($dom);

        // if for request is found only one result is produced forwarding
        $refresh = $xpath->query('//meta[@http-equiv="Refresh"]/@content');
        if ($refresh->length) {
            list(, $url) = explode('url=', $refresh->item(0)->nodeValue, 2);
            // add http if need
            if ($url[0] == '/') {
                $url = self::HOST.substr($url, 1);
            }
            $name = iconv('cp1251', 'utf-8', $name);
            if (!preg_match('/id=(?<id>\d+)/', $url, $mat)) {
                throw new NotFoundHttpException('Incorrect URL for found item');
            }
            return [
                new ItemSearch(
                    $name,
                    $this->getLinkForFill($url),
                    self::HOST.'animation/img/'.(ceil($mat['id']/1000)*1000).'/'.$mat['id'].'/1.jpg',
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
                preg_match('/id=(?<id>\d+)/', $href, $mat)
            ) {
                $list[] = new ItemSearch(
                    str_replace(["\r\n", "\n"], ' ', $name),
                    $this->getLinkForFill(self::HOST.$href),
                    self::HOST.'animation/img/'.(ceil($mat['id']/1000)*1000).'/'.$mat['id'].'/1.jpg',
                    trim(str_replace($name, '', $el->nodeValue))
                );
            }
        }

        return $list;
    }
}