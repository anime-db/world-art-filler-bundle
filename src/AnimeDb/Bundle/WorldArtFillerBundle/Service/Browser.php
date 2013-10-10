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

use Buzz\Browser as BrowserBuzz;
use Symfony\Component\HttpFoundation\Request;

/**
 * Browser
 *
 * @link http://world-art.ru/
 * @package AnimeDb\Bundle\WorldArtFillerBundle\Service
 * @author  Peter Gribanov <info@peter-gribanov.ru>
 */
class Browser
{
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
     */
    public function __construct(BrowserBuzz $browser) {
        $this->browser = $browser;
    }

    /**
     * Set request
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     */
    public function setRequest(Request $request = null)
    {
        $this->request = $request;
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
    public function getDom($url) {
        $dom = new \DOMDocument('1.0', 'utf8');
        if (($content = $this->getContent($url)) && $dom->loadHTML($content)) {
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
     *
     * @return string
     */
    public function getContent($url) {
        $headers = ['User-Agent' => self::DEFAULT_USER_AGENT];
        // try to set User-Agent from original request
        if ($this->request) {
            $headers['User-Agent'] = $this->request->server->get('HTTP_USER_AGENT', self::DEFAULT_USER_AGENT);
        }
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