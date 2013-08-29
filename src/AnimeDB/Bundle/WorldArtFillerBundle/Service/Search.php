<?php
/**
 * AnimeDB package
 *
 * @package   AnimeDB
 * @author    Peter Gribanov <info@peter-gribanov.ru>
 * @copyright Copyright (c) 2011, Peter Gribanov
 * @license   http://opensource.org/licenses/GPL-3.0 GPL v3
 */

namespace AnimeDB\Bundle\WorldArtFillerBundle\Service;

use AnimeDB\Bundle\CatalogBundle\Plugin\Search\Search as SearchPlugin;
use AnimeDB\Bundle\CatalogBundle\Plugin\Search\Item as ItemSearch;
use Buzz\Browser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Search from site world-art.ru
 * 
 * @link http://world-art.ru/
 * @package AnimeDB\Bundle\WorldArtFillerBundle\Service
 * @author  Peter Gribanov <info@peter-gribanov.ru>
 */
class Search implements SearchPlugin
{
    /**
     * Name
     *
     * @var string
     */
    const NAME = 'worldart';

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
     * @var \Buzz\Browser
     */
    private $browser;

    /**
     * Request
     *
     * @var \Symfony\Component\HttpFoundation\Request
     */
    private $request;

    /**
     * Construct
     *
     * @param \Buzz\Browser $browser
     * @param \Symfony\Component\HttpFoundation\Request $request
     */
    public function __construct(Browser $browser, Request $request) {
        $this->browser = $browser;
        $this->request = $request;
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
     * Use $url_bulder for build link to fill item from source or build their own links
     *
     * Return structure
     * <code>
     * [
     *     \AnimeDB\Bundle\CatalogBundle\Plugin\Search\Item
     * ]
     * </code>
     *
     * @param string $name
     * @param \Closure $url_bulder
     *
     * @return array
     */
    public function search($name, \Closure $url_bulder)
    {
        $name = iconv('utf-8', 'cp1251', $name);
        $url = str_replace('#NAME#', urlencode($name), self::SEARH_URL);
        // get list from xpath
        $dom = $this->getDomDocumentFromUrl(self::HOST.$url);
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
                    $url,
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
                    $url_bulder(self::HOST.$href),
                    self::HOST.'animation/img/'.(ceil($mat['id']/1000)*1000).'/'.$mat['id'].'/1.jpg',
                    trim(str_replace($name, '', $el->nodeValue))
                );
            }
        }

        return $list;
    }

    /**
     * Get DOMDocument from url
     *
     * Receive content from the URL, cleaning using Tidy and creating DOM document
     *
     * @param string $url
     *
     * @return \DOMDocument|null
     */
    private function getDomDocumentFromUrl($url) {
        $dom = new \DOMDocument('1.0', 'utf8');
        if (($content = $this->getContentFromUrl($url)) && $dom->loadHTML($content)) {
            return $dom;
        } else {
            return null;
        }
    }

    /**
     * Get content from url
     *
     * Receive content from the URL and cleaning using Tidy
     *
     * @param string $url
     */
    private function getContentFromUrl($url) {
        // send headers from original request
        $headers = [
            'User-Agent' => $this->request->server->get('HTTP_USER_AGENT', self::DEFAULT_USER_AGENT)
        ];
        /* @var $response \Buzz\Message\Response */
        $response = $this->browser->get($url, $headers);
        if ($response->getStatusCode() !== 200 || !($html = $response->getContent())) {
            return null;
        }
        $html = iconv('windows-1251', 'utf-8', $html);

        // clean content
        $config = [
            'output-xhtml' => true,
            'indent' => true,
            'indent-spaces' => 0,
            'fix-backslash' => true,
            'hide-comments' => true,
            'drop-empty-paras' => true,
        ];
        $tidy = new \tidy();
        $tidy->ParseString($html, $config, 'utf8');
        $tidy->cleanRepair();
        $html = $tidy->root()->value;
        // ignore blocks
        $html = preg_replace('/<noembed>.*?<\/noembed>/is', '', $html);
        $html = preg_replace('/<noindex>.*?<\/noindex>/is', '', $html);
        // remove noembed
        return $html;
    }
}