<?php
/**
 * AnimeDb package
 *
 * @package   AnimeDb
 * @author    Peter Gribanov <info@peter-gribanov.ru>
 * @copyright Copyright (c) 2011, Peter Gribanov
 * @license   http://opensource.org/licenses/GPL-3.0 GPL v3
 */

namespace AnimeDb\Bundle\WorldArtFillerBundle\Form;

use AnimeDb\Bundle\CatalogBundle\Form\Plugin\Search as SearchForm;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Search from
 *
 * @package AnimeDb\Bundle\WorldArtFillerBundle\Form
 * @author  Peter Gribanov <info@peter-gribanov.ru>
 */
class Search extends SearchForm
{
    /**
     * (non-PHPdoc)
     * @see \Symfony\Component\Form\AbstractType::buildForm()
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);
        $builder->add('type', 'choice', [
            'choices'  => [
                'animation' => 'Anime',
                'cinema' => 'Cinema'
            ],
            'required' => false
        ]);
    }
}