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

use AnimeDb\Bundle\CatalogBundle\Plugin\Fill\Filler\Filler as FillerPlugin;
use Doctrine\Bundle\DoctrineBundle\Registry;
use AnimeDb\Bundle\CatalogBundle\Entity\Item;
use AnimeDb\Bundle\CatalogBundle\Entity\Source;
use AnimeDb\Bundle\CatalogBundle\Entity\Name;
use AnimeDb\Bundle\CatalogBundle\Entity\Country;
use AnimeDb\Bundle\CatalogBundle\Entity\Genre;
use AnimeDb\Bundle\CatalogBundle\Entity\Type;
use AnimeDb\Bundle\CatalogBundle\Entity\Image;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Filesystem\Filesystem;
use AnimeDb\Bundle\CatalogBundle\Entity\Field\Image as ImageField;
use Symfony\Component\Validator\Validator;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use AnimeDb\Bundle\WorldArtFillerBundle\Form\Filler as FillerForm;

/**
 * Filler from site world-art.ru
 * 
 * @link http://world-art.ru/
 * @package AnimeDb\Bundle\WorldArtFillerBundle\Service
 * @author  Peter Gribanov <info@peter-gribanov.ru>
 */
class Filler extends FillerPlugin
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
     * XPath for fill item
     *
     * @var string
     */
    const XPATH_FOR_FILL = '//center/table[@height="58%"]/tr/td/table[1]/tr/td';

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
     * Doctrine
     *
     * @var \Doctrine\Bundle\DoctrineBundle\Registry
     */
    private $doctrine;

    /**
     * Validator
     *
     * @var \Symfony\Component\Validator\Validator
     */
    private $validator;

    /**
     * Filesystem
     *
     * @var \Symfony\Component\Filesystem\Filesystem
     */
    private $fs;

    /**
     * World-Art genres
     *
     * @var array
     */
    private $genres = [
        'боевик' => 'Action',
        'боевые искусства' => 'Martial arts',
        'вампиры' => 'Vampires',
        'война' => 'War',
        'детектив' => 'Detective',
        'для детей' => 'For children',
        'дзёсэй' => 'Josei',
        'драма' => 'Drama',
        'история' => 'History',
        'киберпанк' => 'Cyberpunk',
        'комедия' => 'Comedy',
        'махо-сёдзё' => 'Mahoe shoujo',
        'меха' => 'Meho',
        'мистерия' => 'Mystery play',
        'мистика' => 'Mysticism',
        'музыкальный' => 'Musical',
        'образовательный' => 'Educational',
        'пародия' => 'Parody',
        'cтимпанк' => 'Steampunk',
        'паропанк' => 'Steampunk',
        'повседневность' => 'Everyday',
        'полиция' => 'Police',
        'постапокалиптика' => 'Apocalyptic fiction',
        'приключения' => 'Adventure',
        'психология' => 'Psychology',
        'романтика' => 'Romance',
        'самурайский боевик' => 'Samurai action',
        'сёдзё' => 'Shoujo',
        'сёдзё-ай' => 'Shoujo-ai',
        'сёнэн' => 'Senen',
        'сёнэн-ай' => 'Senen-ai',
        'сказка' => 'Fable',
        'спорт' => 'Sport',
        'сэйнэн' => 'Senen',
        'триллер' => 'Thriller',
        'школа' => 'School',
        'фантастика' => 'Fantastic',
        'фэнтези' => 'Fantasy',
        'эротика' => 'Erotica',
        'этти' => 'Ettie',
        'ужасы' => 'Horror',
        'хентай' => 'Hentai',
        'юри' => 'Urey',
        'яой' => 'Yaoi',
    ];

    /**
     * World-Art types
     *
     * @var array
     */
    private $types = [
        'ТВ' => 'tv',
        'ТВ-спэшл' => 'special',
        'OVA' => 'ova',
        'ONA' => 'ona',
        'OAV' => 'ova',
        'полнометражный фильм' => 'feature',
        'короткометражный фильм' => 'featurette',
        'музыкальное видео' => 'music',
        'рекламный ролик' => 'commercial',
    ];

    /**
     * Construct
     *
     * @param \AnimeDb\Bundle\WorldArtFillerBundle\Service\Browser $browser
     * @param \Doctrine\Bundle\DoctrineBundle\Registry $doctrine
     * @param \Symfony\Component\Validator\Validator $validator
     */
    public function __construct(
        Browser $browser,
        Registry $doctrine,
        Validator $validator
    ) {
        $this->browser  = $browser;
        $this->doctrine = $doctrine;
        $this->validator = $validator;
        $this->fs = new Filesystem();
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
     * Get form
     *
     * @return \AnimeDb\Bundle\WorldArtFillerBundle\Form\Filler
     */
    public function getForm()
    {
        return new FillerForm();
    }

    /**
     * Fill item from source
     *
     * @param array $date
     *
     * @return \AnimeDb\Bundle\CatalogBundle\Entity\Item|null
     */
    public function fill(array $data)
    {
        if (empty($data['url']) || !is_string($data['url']) || strpos($data['url'], self::HOST) !== 0) {
            return null;
        }

        $dom = $this->browser->getDom($data['url']);
        if (!($dom instanceof \DOMDocument)) {
            return null;
        }
        $xpath = new \DOMXPath($dom);
        $nodes = $xpath->query(self::XPATH_FOR_FILL);

        /* @var $body \DOMElement */
        if (!($body = $nodes->item(4))) {
            throw new \LogicException('Incorrect data structure at source');
        }

        // item id from source
        $id = 0;
        if (preg_match('/id=(?<id>\d+)/', $data['url'], $mat)) {
            $id = (int)$mat['id'];
        }

        $item = new Item();

        // add source link on world-art
        $item->addSource((new Source())->setUrl($data['url']));

        // add other source links
        /* @var $links \DOMNodeList */
        $links = $xpath->query('a', $nodes->item(1));
        for ($i = 0; $i < $links->length; $i++) {
            $link = $this->getAttrAsArray($links->item($i));
            if (strpos($link['href'], 'http://') !== false && strpos($link['href'], self::HOST) === false) {
                $item->addSource((new Source())->setUrl($link['href']));
            }
        }

        // add cover
        $item->setCover($this->getCover($id));

        // fill main data
        $head = $xpath->query('table[3]/tr[2]/td[3]', $body);
        if (!$head->length) {
            $head = $xpath->query('table[2]/tr[1]/td[3]', $body);
        }
        $this->fillHeadData($item, $xpath, $head->item(0));

        // fill body data
        $this->fillBodyData($item, $xpath, $body, $id, $data['frames']);
        return $item;
    }

    /**
     * Get element attributes as array
     *
     * @param \DOMElement $element
     *
     * @return array
     */
    private function getAttrAsArray(\DOMElement $element) {
        $return = [];
        for ($i = 0; $i < $element->attributes->length; ++$i) {
            $return[$element->attributes->item($i)->nodeName] = $element->attributes->item($i)->nodeValue;
        }
        return $return;
    }

    /**
     * Get cover from source id
     *
     * @param string $id
     *
     * @return string|null
     */
    private function getCover($id) {
        $cover = self::HOST.'animation/img/'.(ceil($id/1000)*1000).'/'.$id.'/1.jpg';
        try {
            return $this->uploadImage($cover, $id.'/1.jpg');
        } catch (\Exception $e) {}
        return null;
    }

    /**
     * Fill head data
     *
     * @param \AnimeDb\Bundle\CatalogBundle\Entity\Item $item
     * @param \DOMXPath $xpath
     * @param \DOMElement $head
     *
     * @return \AnimeDb\Bundle\CatalogBundle\Entity\Item
     */
    private function fillHeadData(Item $item, \DOMXPath $xpath, \DOMElement $head) {
        // add main name
        $name = $xpath->query('font[1]/b', $head)->item(0)->nodeValue;
        // clear
        $name = preg_replace('/\[?(ТВ|OVA|ONA)(\-\d)?\]?/', '', $name); // example: [TV-1]
        $name = preg_replace('/\(фильм \w+\)/u', '', $name); // example: (фильм седьмой)
        $name = trim($name, " [\r\n\t"); // clear trash
        $item->setName($name);

        // find other names
        foreach ($head->childNodes as $node) {
            if ($node->nodeName == '#text' && trim($node->nodeValue)) {
                $name = trim(preg_replace('/(\(\d+\))?/', '', $node->nodeValue));
                $item->addName((new Name())->setName($name));
            }
        }

        /* @var $data \DOMElement */
        $data = $xpath->query('font[2]', $head)->item(0);
        $length = $data->childNodes->length;
        for ($i = 0; $i < $length; $i++) {
            if ($data->childNodes->item($i)->nodeName == 'b') {
                switch ($data->childNodes->item($i)->nodeValue) {
                    // set manufacturer
                    case 'Производство':
                        $j = 1;
                        do {
                            if ($data->childNodes->item($i+$j)->nodeName == 'img') {
                                $country_name = trim($data->childNodes->item($i+$j+1)->nodeValue);
                                if ($country_name && $country = $this->getCountryByName($country_name)) {
                                    $item->setManufacturer($country);
                                }
                                break;
                            }
                            $j++;
                        } while ($data->childNodes->item($i+$j)->nodeName != 'br');
                        $i += $j;
                        break;
                    // add genre
                    case 'Жанр':
                        $j = 2;
                        do {
                            if ($data->childNodes->item($i+$j)->nodeName == 'a' &&
                                ($genre = $this->getGenreByName($data->childNodes->item($i+$j)->nodeValue))
                            ) {
                                $item->addGenre($genre);
                            }
                            $j++;
                        } while ($data->childNodes->item($i+$j)->nodeName != 'br');
                        $i += $j;
                        break;
                    // set type and add file info
                    case 'Тип':
                        $type = $data->childNodes->item($i+1)->nodeValue;
                        if (preg_match('/(?<type>[\w\s]+)(?: \((?:(?<episodes_number>>?\d+) эп.)?(?<file_info>.*)\))?, (?<duration>\d{1,3}) мин\.$/u', $type, $match)) {
                            // add type
                            if ($type = $this->getTypeByName(trim($match['type']))) {
                                $item->setType($type);
                            }
                            // add duration
                            $item->setDuration((int)$match['duration']);
                            // add number of episodes
                            if (!empty($match['episodes_number'])) {
                                if ($match['episodes_number'][0] == '>') {
                                    $item->setEpisodesNumber(substr($match['episodes_number'], 1).'+');
                                } else {
                                    $item->setEpisodesNumber((int)$match['episodes_number']);
                                }
                            } elseif ($item->getType()->getId() != 'tv') {
                                // everything except the TV series consist of a single episode
                                $item->setEpisodesNumber(1);
                            }
                            // add file info
                            if (!empty($match['file_info'])) {
                                $file_info = $item->getFileInfo();
                                $item->setFileInfo(($file_info ? $file_info."\n" : '').trim($match['file_info']));
                            }
                        }
                        $i++;
                        break;
                    // set date start and date end if exists
                    case 'Премьера':
                    case 'Выпуск':
                        $j = 1;
                        $date = '';
                        do {
                            $date .= $data->childNodes->item($i+$j)->nodeValue;
                            $j++;
                        } while ($length > $i+$j && $data->childNodes->item($i+$j)->nodeName != 'br');
                        $i += $j;

                        $reg = '/(?<start>(?:(?:\d{2})|(?:\?\?)).\d{2}.\d{4})'.
                            '(?:.*(?<end>(?:(?:\d{2})|(?:\?\?)).\d{2}.\d{4}))?/';
                        if (preg_match($reg, $date, $match)) {
                            $item->setDateStart(new \DateTime(str_replace('??', '01', $match['start'])));
                            if (isset($match['end'])) {
                                $item->setDateEnd(new \DateTime($match['end']));
                            }
                        }
                        break;
                }
            }
        }
    }
    
    /**
     * Fill body data
     *
     * @param \AnimeDb\Bundle\CatalogBundle\Entity\Item $item
     * @param \DOMXPath $xpath
     * @param \DOMElement $body
     * @param integer $id
     * @param boolean $frames
     *
     * @return \AnimeDb\Bundle\CatalogBundle\Entity\Item
     */
    private function fillBodyData(Item $item, \DOMXPath $xpath, \DOMElement $body, $id, $frames) {
        for ($i = 0; $i < $body->childNodes->length; $i++) {
            if ($value = trim($body->childNodes->item($i)->nodeValue)) {
                switch ($value) {
                    // get summary
                    case 'Краткое содержание:':
                        $summary = $xpath->query('tr/td/p[1]', $body->childNodes->item($i+2));
                        if ($summary->length) {
                            $item->setSummary(trim($summary->item(0)->nodeValue));
                        }
                        $i += 2;
                        break;
                    // get episodes
                    case 'Эпизоды:':
                        if (!trim($body->childNodes->item($i+1)->nodeValue)) { // simple list
                            $item->setEpisodes(trim($body->childNodes->item($i+2)->nodeValue));
                            $i += 2;
                        } else { // episodes in table
                            $rows = $xpath->query('tr/td[2]', $body->childNodes->item($i+1));
                            $episodes = '';
                            for ($j = 1; $j < $rows->length; $j++) {
                                $episode = $xpath->query('font', $rows->item($j));
                                $episodes .= $j.'. '.$episode->item(0)->nodeValue;
                                if ($rows->length > 1) {
                                    $episodes .= ' ('.$episode->item(1)->nodeValue.')';
                                }
                                $episodes .= "\n";
                            }
                            $item->setEpisodes($episodes);
                            $i++;
                        }
                        break;
                    default:
                        // get frames
                        if (strpos($value, 'кадры из аниме') !== false && $id && $frames) {
                            $dom = $this->browser->getDom(self::HOST.'animation/animation_photos.php?id='.$id);
                            $images = (new \DOMXPath($dom))->query('//table//table//table//img');
                            foreach ($images as $image) {
                                $src = $this->getAttrAsArray($image)['src'];
                                $src = str_replace('optimize_b', 'optimize_d', $src);
                                if (strpos($src, 'http://') === false) {
                                    $src = self::HOST.'animation/'.$src;
                                }
                                if (preg_match('/\-(?<image>\d+)\-optimize_d(?<ext>\.jpe?g|png|gif)/', $src, $mat) &&
                                    $src = $this->uploadImage($src, $id.'/'.$mat['image'].$mat['ext'])
                                ) {
                                    $item->addImage((new Image())->setSource($src));
                                }
                            }
                        }
                }
            }
        }
    }

    /**
     * Upload image from url
     *
     * @param string $url
     * @param string|null $target
     *
     * @return string
     */
    private function uploadImage($url, $target = null) {
        $image = new ImageField();
        $image->setRemote($url);
        $image->upload($this->validator, $target);
        return $image->getPath();
    }

    /**
     * Create unique file name
     *
     * @param string $path
     * @param string $ext
     *
     * @return string
     */
    private function createFileName($path, $ext) {
        do {
            $file_name = uniqid();
        } while (file_exists($path.$file_name.'.'.$ext));
        return $path.$file_name.'.'.$ext;
    }

    /**
     * Get real country by name
     *
     * @param integer $id
     *
     * @return \AnimeDb\Bundle\CatalogBundle\Entity\Country|null
     */
    private function getCountryByName($name) {
        $rep = $this->doctrine->getRepository('AnimeDbCatalogBundle:CountryTranslation');
        if ($country = $rep->findOneBy(['locale' => 'ru', 'content' => $name])) {
            return $country->getObject();
        }
        return null;
    }

    /**
     * Get real genre by name
     *
     * @param string $name
     *
     * @return \AnimeDb\Bundle\CatalogBundle\Entity\Genre|null
     */
    private function getGenreByName($name) {
        if (isset($this->genres[$name])) {
            return $this->doctrine
                ->getRepository('AnimeDbCatalogBundle:Genre')
                ->findOneByName($this->genres[$name]);
        }
        return null;
    }

    /**
     * Get real type by name
     *
     * @param string $name
     *
     * @return \AnimeDb\Bundle\CatalogBundle\Entity\Type|null
     */
    private function getTypeByName($name) {
        if (isset($this->types[$name])) {
            return $this->doctrine
                ->getRepository('AnimeDbCatalogBundle:Type')
                ->find($this->types[$name]);
        }
        return null;
    }
}