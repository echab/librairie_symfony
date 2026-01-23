<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;

class AdminUnzipType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('zipFile', ChoiceType::class, [
                'label' => 'Fichiers zip dans /backup:',
                'required' => true,
                'choices'  => $options['data'],
                // 'choice_label' => fn ($choice, string $key, mixed $value) => $key,
                // 'expanded' => true,
            ])

            ->add(
                $builder->create('actions', FormType::class, [
                    'inherit_data' => true,
                    'label' => false,
                    'attr' => ['class' => 'flex'],
                ])
                    ->add('unzip', SubmitType::class, [
                        'label' => '🔺Unzip',
                    ])
                    ->add('download', SubmitType::class, [
                        'label' => 'Télécharge',
                    ])
            );
    }
}
