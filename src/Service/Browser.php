<?php
/**
 * AnimeDb package.
 *
 * @author    Peter Gribanov <info@peter-gribanov.ru>
 * @copyright Copyright (c) 2011, Peter Gribanov
 * @license   http://opensource.org/licenses/GPL-3.0 GPL v3
 */

namespace AnimeDb\Bundle\WorldArtFillerBundle\Service;

use Symfony\Component\HttpFoundation\Request;
use Guzzle\Http\Client;

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
     * @var \Guzzle\Http\Client
     */
    protected $browser;

    /**
     * Request
     *
     * @var \Symfony\Component\HttpFoundation\Request
     */
    protected $request;

    /**
     * HTTP host
     *
     * @var string
     */
    protected $host;

    /**
     * Browser timeout
     *
     * @var integer
     */
    protected $timeout;

    /**
     * Browser proxy list
     *
     * @var array
     */
    protected $proxy_list;

    /**
     * Construct
     *
     * @param string $host
     * @param integer $timeout
     * @param array $proxy_list
     */
    public function __construct($host, $timeout, array $proxy_list)
    {
        $this->host = $host;
        $this->proxy_list = $proxy_list;
        $this->timeout = $timeout;
    }

    /**
     * Get host
     *
     * @return string
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * Set request
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     */
    public function setRequest(Request $request = null)
    {
        $this->request = $request;
        // try to set User-Agent from original request
        if ($request && $this->browser) {
            $this->browser->setDefaultHeaders([
                'User-Agent' => $request->server->get('HTTP_USER_AGENT', self::DEFAULT_USER_AGENT)
            ]);
        }
    }

    /**
     * Get DOMDocument from path
     *
     * Receive content from the URL, cleaning using Tidy and creating DOM document
     *
     * @param string $path
     *
     * @return \DOMDocument|null
     */
    public function getDom($path)
    {
        $dom = new \DOMDocument('1.0', 'utf8');
        if (($content = $this->getContent($path)) && $dom->loadHTML($content)) {
            return $dom;
        } else {
            return null;
        }
    }

    /**
     * Get content from path
     *
     * Receive content from the URL and cleaning using Tidy
     *
     * @param string $path
     *
     * @return string
     */
    public function getContent($path)
    {
        /* @var $response \Guzzle\Http\Message\Response */
        $response = $this->getBrowser()->get($path)->send();
        if ($response->isError()) {
            throw new \RuntimeException('Failed to query the server '.$this->host);
        }
        if ($response->getStatusCode() !== 200 || !($html = $response->getBody(true))) {
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
            'wrap' => false
        ];
        $tidy = new \tidy();
        $tidy->parseString($html, $config, 'utf8');
        $tidy->cleanRepair();
        $html = $tidy->root()->value;
        // ignore blocks
        $html = preg_replace('/<noembed>.*?<\/noembed>/is', '', $html);
        $html = preg_replace('/<noindex>.*?<\/noindex>/is', '', $html);
        // remove noembed
        return $html;
    }

    /**
     * Get HTTP browser
     *
     * @param \Guzzle\Http\Client
     */
    protected function getBrowser()
    {
        if (!($this->browser instanceof Client)) {
            $this->browser = new Client($this->host);

            // try to set User-Agent from original request
            $user_agent = self::DEFAULT_USER_AGENT;
            if ($this->request) {
                $user_agent = $this->request->server->get('HTTP_USER_AGENT', self::DEFAULT_USER_AGENT);
            }
            $this->browser->setDefaultHeaders(['User-Agent' => $user_agent]);

            // configure browser client
            $this->browser->setDefaultOption('timeout', $this->timeout);
            if ($this->proxy_list) {
                $this->browser->setDefaultOption('proxy', $this->proxy_list[array_rand($this->proxy_list)]);
            }
        }
        return $this->browser;
    }
}
