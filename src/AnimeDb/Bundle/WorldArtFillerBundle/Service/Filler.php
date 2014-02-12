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
use AnimeDb\Bundle\AppBundle\Entity\Field\Image as ImageField;
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
     * Item type animation
     *
     * @var string
     */
    const ITEM_TYPE_ANIMATION = 'animation';

    /**
     * Item type cinema
     *
     * @var string
     */
    const ITEM_TYPE_CINEMA = 'cinema';

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
     * World-Art studios
     *
     * @var array
     */
    private $studios = [
        1 => 'Studio Ghibli',
        3 => 'Gainax',
        4 => 'AIC',
        6 => 'KSS',
        14 => 'TMS Entertainment',
        20 => 'Bones',
        21  => 'Clamp',
        22 => 'Studio DEEN',
        24 => 'J.C.Staff',
        25 => 'Madhouse',
        26  => 'animate',
        29 => 'OLM, Inc.',
        30 => 'Tezuka Productions',
        31 => 'Production I.G',
        32 => 'Gonzo',
        34 => 'Sunrise',
        37 => 'Agent 21',
        41 => 'Toei Animation',
        44 => 'APPP',
        54 => 'Radix',
        56 => 'Pierrot',
        59 => 'Xebec',
        64 => 'Satelight',
        74 => 'Oh! Production',
        78 => 'Triangle Staff',
        82 => 'Bee Train',
        84 => 'Animax Entertainment',
        87 => 'Daume',
        87 => 'Ajia-do',
        89 => 'Kitty Films',
        96 => 'Studio 4°C',
        106 => 'CoMix Wave Inc.',
        116 => 'Fox Animation Studios',
        117 => 'Blue Sky Studios',
        118 => 'Pacific Data Images',
        120 => 'Pixar',
        152 => 'Mushi Production',
        154 => 'Aardman Animations',
        159 => 'DR Movie',
        171 => 'Tatsunoko Productions',
        178 => 'Paramount Animation',
        193 => 'Hal Film Maker',
        198 => 'Studio Fantasia',
        210 => 'Arms Corporation',
        212 => 'Green Bunny',
        236 => 'Pink Pineapple',
        244 => 'Production Reed',
        // reverse links
        250 => 'Melnitsa Animation Studio',
        252 => 'Nippon Animation',
        255 => 'Artland',
        267 => 'Shaft',
        278 => 'March Entertainment',
        296 => 'Gallop',
        315 => 'DreamWorks Animation',
        351 => 'TNK',
        398 => 'A.C.G.T',
        436 => 'Kyoto Animation',
        439 => 'Studio Comet',
        463 => 'Magic Bus',
        639 => 'Industrial Light & Magic',
        689 => 'Zexcs',
        724 => 'Six Point Harness',
        753 => 'Pentamedia Graphics',
        795 => 'Rough Draft Studios',
        802 => 'Shin-Ei Animation',
        821 => 'Warner Bros. Animation',
        1066 => 'Animal Logic',
        1161 => 'Marvel Animation Studios',
        1168 => 'Klasky Csupo',
        1654 => 'Digital Frontier',
        1663 => 'Mac Guff',
        1689 => 'Manglobe',
        1778 => 'CinéGroupe',
        1889 => 'Film Roman, Inc.',
        1890 => 'AKOM',
        1901 => 'Brain\'s Base',
        1961 => 'Feel',
        2058 => 'Eiken',
        2229 => 'Studio Hibari',
        2370 => 'Imagin',
        2379 => 'Folimage',
        2381 => 'DisneyToon Studios',
        2491 => 'Ufotable',
        3058 => 'Asahi Production',
        3096 => 'Mook Animation',
        3113 => 'Walt Disney Television Animation',
        3420 => 'Metro-Goldwyn-Mayer Animation',
        3530 => 'Seven Arcs',
        3742 => 'Nomad',
        3748 => 'Dygra Films',
        3773 => 'Dogakobo',
        3816 => 'EMation',
        4013 => 'Toon City',
        5423 => 'O Entertainment/Omation Animation Studio',
        6081 => 'Sony Pictures Animation',
        6474 => 'Wang Film Productions',
        6475 => 'Creative Capers Entertainment',
        6701 => 'Arc Productions',
        7092 => 'Millimages',
        7194 => 'Mondo TV',
        7298 => 'A-1 Pictures',
        7372 => 'Diomedea',
        7388 => 'Williams Street Studios',
        7801 => 'National Film Board of Canada',
        7933 => 'Titmouse',
        8590 => 'Rhythm and Hues Studios',
        8639 => 'Bagdasarian Productions',
        9298 => 'Toonz',
        9900 => 'Savage Studios Ltd.',
        10664 => 'A. Film',
        11077 => 'Vanguard Animation',
        11213 => 'bolexbrothers',
        11827 => 'Zinkia Entertainment',
        12209 => 'P.A. Works',
        12268 => 'Universal Animation Studios',
        12280 => 'Reel FX',
        12281 => 'Walt Disney Animation Studios',
        12299 => 'LAIKA',
        12825 => 'White Fox',
        13269 => 'David Production',
        13301 => 'Silver Link',
        13329 => 'Kinema Citrus',
        13906 => 'GoHands',
        13957 => 'Khara',
        14617 => 'Ordet',
        15102 => 'TYO Animations',
        15334 => 'Dong Woo Animation',
        16112 => 'Studio Gokumi',
        16433 => 'Nickelodeon Animation Studios',
        16961 => 'Renegade Animation',
        17049 => 'Curious Pictures',
        17235 => 'Trigger',
        17322 => 'Wit Studio',
    ];

    /**
     * Construct
     *
     * @param \AnimeDb\Bundle\WorldArtFillerBundle\Service\Browser $browser
     * @param \Doctrine\Bundle\DoctrineBundle\Registry $doctrine
     * @param \Symfony\Component\Validator\Validator $validator
     * @param \Symfony\Component\Filesystem\Filesystem $fs
     */
    public function __construct(
        Browser $browser,
        Registry $doctrine,
        Validator $validator,
        Filesystem $fs
    ) {
        $this->browser  = $browser;
        $this->doctrine = $doctrine;
        $this->validator = $validator;
        $this->fs = $fs;
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

        // get item type
        $type = $this->getItemType($data['url']);

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
        $item->setCover($this->getCover($id, $type));

        // fill item studio
        if ($studio = $this->getStudio($xpath, $body)) {
            $item->setStudio($studio);
        }

        // fill main data
        if ($type == self::ITEM_TYPE_ANIMATION) {
            $head = $xpath->query('table[3]/tr[2]/td[3]', $body);
            if (!$head->length) {
                $head = $xpath->query('table[2]/tr[1]/td[3]', $body);
            }
        } else {
            $head = $xpath->query('table[3]/tr[1]/td[3]', $body);
        }

        if ($head->length) {
            switch ($type) {
                case self::ITEM_TYPE_ANIMATION:
                    $this->fillAnimationNames($item, $xpath, $head->item(0));
                    break;
                default:
                    $this->fillCinemaNames($item, $xpath, $head->item(0));
            }
        }
        $this->fillHeadData($item, $xpath, $head->item(0));

        // fill body data
        $this->fillBodyData($item, $xpath, $body, $id, $data['frames'], $type);
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
     * @param string $type
     *
     * @return string|null
     */
    private function getCover($id, $type) {
        switch ($type) {
            case self::ITEM_TYPE_ANIMATION:
                $cover = self::HOST.$type.'/img/'.(ceil($id/1000)*1000).'/'.$id.'/1.jpg';
                break;
            default:
                $cover = self::HOST.$type.'/img/'.(ceil($id/10000)*10000).'/'.$id.'/1.jpg';
        }

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
        /* @var $data \DOMElement */
        $data = $xpath->query('font', $head)->item(0);
        $length = $data->childNodes->length;
        for ($i = 0; $i < $length; $i++) {
            if ($data->childNodes->item($i)->nodeName == 'b') {
                switch ($data->childNodes->item($i)->nodeValue) {
                    // set country
                    case 'Производство':
                        $j = 1;
                        do {
                            if ($data->childNodes->item($i+$j)->nodeName == 'a') {
                                $country_name = trim($data->childNodes->item($i+$j)->nodeValue);
                                if ($country_name && $country = $this->getCountryByName($country_name)) {
                                    $item->setCountry($country);
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
                    // set date premiere and date end if exists
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
                            $item->setDatePremiere(new \DateTime(str_replace('??', '01', $match['start'])));
                            if (isset($match['end'])) {
                                $item->setDateEnd(new \DateTime($match['end']));
                            }
                        }
                        break;
                    case 'Хронометраж':
                        if (preg_match('/(?<duration>\d+)/', $data->childNodes->item($i+1)->nodeValue, $match)) {
                            $item->setDuration((int)$match['duration']);
                        }
                        break;
                    case 'Кол-во серий':
                        $number = trim($data->childNodes->item($i+1)->nodeValue, ' :');
                        if (strpos($number, '>') !== false) {
                            $number = str_replace('>', '', $number).'+';
                        }
                        $item->setEpisodesNumber($number);
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
     * @param string $type
     *
     * @return \AnimeDb\Bundle\CatalogBundle\Entity\Item
     */
    private function fillBodyData(Item $item, \DOMXPath $xpath, \DOMElement $body, $id, $frames, $type) {
        for ($i = 0; $i < $body->childNodes->length; $i++) {
            if ($value = trim($body->childNodes->item($i)->nodeValue)) {
                switch ($value) {
                    // get summary
                    case 'Краткое содержание:':
                        $summary = $xpath->query('tr/td/p[1]', $body->childNodes->item($i+2));
                        if ($summary->length) {
                            $item->setSummary($this->getNodeValueAsText($summary->item(0)));
                        }
                        $i += 2;
                        break;
                    // get episodes
                    case 'Эпизоды:':
                        if (!trim($body->childNodes->item($i+1)->nodeValue)) { // simple list
                            $item->setEpisodes($this->getNodeValueAsText($body->childNodes->item($i+2)));
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
                    // get date premiere
                    case 'Даты премьер и релизов':
                        $rows = $xpath->query('tr/td/table/tr/td[3]', $body->childNodes->item($i+1));
                        foreach ($rows as $row) {
                            if (preg_match('/\d{4}(?:\.\d{2}\.\d{2})?/', $row->nodeValue, $match)) {
                                if (strlen($match[0]) == 4) {
                                    $date = new \DateTime($match[0].'-1-1');
                                } else {
                                    $date = new \DateTime(str_replace('.', '-', $match[0]));
                                }

                                if (!$item->getDatePremiere() || $item->getDatePremiere() > $date) {
                                    $item->setDatePremiere($date);
                                }
                            }
                        }
                        break;
                    default:
                        // get frames
                        if (strpos($value, 'кадры из аниме') !== false && $id && $frames) {
                            foreach ($this->getFrames($id, $type) as $frame) {
                                $item->addImage((new Image())->setSource($frame));
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
    public function uploadImage($url, $target = null) {
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
        $name = str_replace('Южная Корея', 'Республика Корея', $name);
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

    /**
     * Get item frames
     *
     * @param integer $id
     * @param string $type
     *
     * @return array
     */
    public function getFrames($id, $type)
    {
        $dom = $this->browser->getDom(self::HOST.'animation/animation_photos.php?id='.$id);
        if (!$dom) {
            return [];
        }
        $images = (new \DOMXPath($dom))->query('//table//table//table//img');
        $frames = [];
        foreach ($images as $image) {
            $src = $this->getAttrAsArray($image)['src'];
            $src = str_replace('optimize_b', 'optimize_d', $src);
            if (strpos($src, 'http://') === false) {
                $src = self::HOST.$type.'/'.$src;
            }
            if (preg_match('/\-(?<image>\d+)\-optimize_d(?<ext>\.jpe?g|png|gif)/', $src, $mat) &&
                $src = $this->uploadImage($src, $id.'/'.$mat['image'].$mat['ext'])
            ) {
                $frames[] = $src;
            }
        }
        return $frames;
    }

    /**
     * Get node value as text
     *
     * @param \DOMNode $node
     *
     * @return string
     */
    private function getNodeValueAsText(\DOMNode $node)
    {
        $text = $node->ownerDocument->saveHTML($node);
        $text = str_replace(["<br>\n", "\n", '<br>'], ['<br>', ' ', "\n"], $text);
        return trim(strip_tags($text));
    }

    /**
     * Get item studio
     *
     * @param \DOMXPath $xpath
     * @param \DOMNode $body
     *
     * @return \AnimeDb\Bundle\CatalogBundle\Entity\Studio|null
     */
    private function getStudio(\DOMXPath $xpath, \DOMNode $body)
    {
        $studios = $xpath->query('//img[starts-with(@src,"http://www.world-art.ru/img/company_new/")]', $body);
        if ($studios->length) {
            foreach ($studios as $studio) {
                $url = $studio->attributes->getNamedItem('src')->nodeValue;
                if (preg_match('/\/(\d+)\./', $url, $mat) && isset($this->studios[$mat[1]])) {
                    return $this->doctrine
                        ->getRepository('AnimeDbCatalogBundle:Studio')
                        ->findOneByName($this->studios[$mat[1]]);
                }
            }
        }
    }

    /**
     * Get link for fill item
     *
     * @param mixed $data
     *
     * @return string
     */
    public function getLinkForFill($data)
    {
        return $this->router->generate(
            'fill_filler',
            [
                'plugin' => $this->getName(),
                $this->getForm()->getName() => ['url' => $data, 'frames' => 0]
            ]
        );
    }

    /**
     * Get item type by URL
     *
     * @param string $url
     *
     * @return string
     */
    protected function getItemType($url)
    {
        if (strpos($url, self::ITEM_TYPE_ANIMATION)) {
            return self::ITEM_TYPE_ANIMATION;
        } else {
            return self::ITEM_TYPE_CINEMA;
        }
    }

    /**
     * Fill names for Animation type
     *
     * @param \AnimeDb\Bundle\CatalogBundle\Entity\Item $item
     * @param \DOMXPath $xpath
     * @param \DOMElement $head
     *
     * @return \AnimeDb\Bundle\CatalogBundle\Entity\Item
     */
    protected function fillAnimationNames(Item $item, \DOMXPath $xpath, \DOMElement $head)
    {
        // add main name
        $names = $xpath->query('table[1]/tr/td', $head)->item(0)->nodeValue;
        $names = explode("\n", trim($names));

        // clear
        $name = preg_replace('/\[\d{4}\]/', '', array_shift($names)); // example: [2011]
        $name = preg_replace('/\[?(ТВ|OVA|ONA)(\-\d)?\]?/', '', $name); // example: [TV-1]
        $name = preg_replace('/\(фильм \w+\)/u', '', $name); // example: (фильм седьмой)
        $item->setName(trim($name));

        // add other names
        foreach ($names as $name) {
            $name = trim(preg_replace('/(\(\d+\))?/', '', $name));
            $item->addName((new Name())->setName($name));
        }
        return $item;
    }

    /**
     * Fill names for Cinema type
     *
     * @param \AnimeDb\Bundle\CatalogBundle\Entity\Item $item
     * @param \DOMXPath $xpath
     * @param \DOMElement $head
     *
     * @return \AnimeDb\Bundle\CatalogBundle\Entity\Item
     */
    protected function fillCinemaNames(Item $item, \DOMXPath $xpath, \DOMElement $head)
    {
        // get list names
        $names = [];
        foreach ($xpath->query('table[1]/tr/td/table/tr/td', $head) as $name) {
            $names[] = $name->nodeValue;
        }

        // clear
        $name = preg_replace('/\[\d{4}\]/', '', array_shift($names)); // example: [2011]
        $name = preg_replace('/\[?(ТВ|OVA|ONA)(\-\d)?\]?/', '', $name); // example: [TV-1]
        $name = preg_replace('/\(фильм \w+\)/u', '', $name); // example: (фильм седьмой)
        $item->setName(trim($name));

        // add other names
        foreach ($names as $name) {
            $name = trim(preg_replace('/(\(\d+\))/', '', $name));
            $item->addName((new Name())->setName($name));
        }
        return $item;
    }
}