<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\File;

class AdminUploadType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('uploadedZip', FileType::class, [
                'label' => 'Uploader un fichier zip:',
                'attr' => ['accept' => 'application/zip'],
                'mapped' => false,
                'required' => true,
                'constraints' => [
                    new File([
                        'maxSize' => '1024k',
                        'extensions' => ['zip'],
                        'extensionsMessage' => 'Please upload a valid zip file',
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
                        'label' => 'Upload',
                    ])
            );
    }
}
