<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\File;

class ImageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nommer l\'image',
                'required' => false,
                'attr' => ['autocomplete' => 'off'],
            ])
            ->add('paste', ButtonType::class, [
                'label' => 'Coller l\'image',
            ])

            ->add('image', FileType::class, [
                'label' => 'Télécharger une image:',
                'attr' => ['accept' => 'image/png, image/jpeg, image/gif'], // image/svg+xml
                'mapped' => false,
                // 'data_class' => null, // TODO or add a view transformer that transforms "string" to an instance of "Symfony\Component\HttpFoundation\File\File
                'required' => true,
                'constraints' => [
                    new File([
                        'maxSize' => '1024k',
                        'extensions' => ['png', 'jpg', 'jpeg', 'gif'],
                        'extensionsMessage' => 'Please upload a valid image file',
                    ])
                ],
            ])

            ->add(
                $builder->create('actions', FormType::class, [
                    'inherit_data' => true,
                    'label' => false,
                    'attr' => ['class' => 'flex'],
                ])
                    ->add('upload', SubmitType::class, [
                        'label' => 'Charger l\'image',
                    ])
            );
    }
}
