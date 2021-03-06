<?php
/**
 * AnimeDb package.
 *
 * @author    Peter Gribanov <info@peter-gribanov.ru>
 * @copyright Copyright (c) 2011, Peter Gribanov
 * @license   http://opensource.org/licenses/GPL-3.0 GPL v3
 */

namespace AnimeDb\Bundle\WorldArtFillerBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * Get item from filler.
 */
class Filler extends AbstractType
{
    /**
     * HTTP host.
     *
     * @var string
     */
    protected $host;

    /**
     * Construct.
     *
     * @param string $host
     */
    public function __construct($host)
    {
        $this->host = $host;
    }

    /**
     * @param FormBuilderInterface $builder
     * @param array                $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->setMethod('GET')
            ->add('url', 'text', [
                'label' => 'URL address',
                'attr' => [
                    'placeholder' => $this->host.'/',
                ],
            ])
            ->add('frames', 'checkbox', [
                'label' => 'Upload frames',
                'required' => false,
            ]);
    }

    /**
     * (non-PHPdoc).
     *
     * @see Symfony\Component\Form.AbstractType::setDefaultOptions()
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults([
            'csrf_protection' => false,
        ]);
    }

    /**
     * (non-PHPdoc).
     *
     * @see \Symfony\Component\Form\FormTypeInterface::getName()
     */
    public function getName()
    {
        return 'animedb_worldartfillerbundle_filler';
    }
}
