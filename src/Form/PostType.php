<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Post;
use App\Entity\Rayon;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class PostType extends AbstractType
{
    public const HTML5_FORMAT = 'yyyy-MM-dd';

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', TextType::class, [
                'label' => 'Titre*:',
                'attr' => ['size' => 100],
            ])
            ->add('category', ChoiceType::class, [
                'label' => 'Catégorie:',
                'choices' => [
                    "A la une" => 'a-la-une',
                    "Information" => 'info',
                    "Agenda" => 'agenda',
                    "Galerie photos" => 'photos',
                ],
                'required' => false,
            ])
            ->add(
                $builder->create('livre', FormType::class, [
                    'inherit_data' => true,
                    'label' => false,
                    'attr' => ['class' => 'flex'],
                ])
                    ->add('auteur', TextType::class, [
                        'label' => 'Auteur:',
                        'attr' => ['size' => 20],
                        'required' => false,
                    ])

                    ->add('ean', TextType::class, [
                        'label' => 'EAN :',
                        'attr' => ['size' => 13, 'pattern' => '[0-9à&é"\'\\(-è_ç]{13}'],
                        'constraints' => new Assert\Regex('/^[0-9à&é"\'\\(\\-è_ç]{13}$/'),
                        'required' => true,
                    ])
                    ->add('reload', ButtonType::class, [
                        'label' => '↻',
                        'attr' => [
                            'style' => 'margin-top:2em; margin-left:-2em;',
                        ],
                    ])

                    ->add('editeur', TextType::class, [
                        'label' => 'Editeur :',
                        'attr' => ['size' => 20],
                        'required' => false,
                    ])
                    ->add('parution', DateType::class, [
                        'label' => 'Date de parution:',
                        'html5' => true,
                        'widget' => 'single_text',
                        'format' => self::HTML5_FORMAT,
                        'required' => false,
                    ])
                    ->add('prix', NumberType::class, [
                        'label' => 'Prix :',
                        'attr' => ['size' => 6],
                        'required' => false,
                    ])
                    ->add('rayonCode', ChoiceType::class, [
                        'label' => 'Rayon:',
                        'choices'  => array_map(fn($r) => $r->code, Rayon::$allRayons),
                        'choice_label' => fn(?int $code) => $code ? $code . ': ' . Rayon::byCode($code)->titre : '',
                        'group_by' => fn($code) => Rayon::byCode($code, true)->titre,
                        'required' => true,
                    ])
            )
            ->add(
                $builder->create('meta', FormType::class, [
                    'inherit_data' => true,
                    'label' => false,
                    'attr' => ['class' => 'flex'],
                ])
                    ->add('libraire', TextType::class, [
                        'label' => 'Votre nom*:',
                        'attr' => ['size' => 20],
                    ])
                    ->add('date', DateType::class, [
                        'label' => 'Date*:',
                        'html5' => true,
                        'widget' => 'single_text',
                        'format' => self::HTML5_FORMAT,
                    ])
                    ->add('expire', DateType::class, [
                        'label' => 'Date d’expiration:',
                        'html5' => true,
                        'widget' => 'single_text',
                        'format' => self::HTML5_FORMAT,
                        'required' => false,
                    ])
            )

            ->add('markdown', TextareaType::class, [
                'label' => 'Votre texte:',
                'attr' => ['rows' => 25, 'cols' => 93],
                'constraints' => new Assert\NotBlank,
                'help' => 'Ajoutez du style en utilisant <a href="https://docs.framasoft.org/fr/grav/markdown.html" target="_blank" rel="noreferrer">Markdown</a>.',
                'help_html' => true,
            ])

            ->add(
                $builder->create('actions', FormType::class, [
                    'inherit_data' => true,
                    'label' => false,
                    'attr' => ['class' => 'flex'],
                ])
                    ->add('reset', ResetType::class, [
                        'label' => 'Annuler',
                    ])
                    ->add('preview', SubmitType::class, [
                        'label' => 'Aperçu',
                    ])
                    ->add('submit', SubmitType::class, [
                        'label' => 'Publier',
                    ])
            );
    }
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Post::class,
        ]);
    }
}
