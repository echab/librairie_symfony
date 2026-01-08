<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ImagePreviewType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // $builder->addViewTransformer(new StringToFileTransformer());
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['image_url'] = $options['image_url'];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'image_url' => '',
        ]);
    }

    // public function getParent(): string
    // {
    //     return FileType::class;
    // }

    public function getBlockPrefix(): string
    {
        return 'image_preview';
    }
}