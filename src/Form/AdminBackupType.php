<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;

class AdminBackupType extends AbstractType
{
    public const HTML5_FORMAT = 'yyyy-MM-dd';

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('fromDate', DateType::class, [
                'label' => 'A partir du :',
                'html5' => true,
                'widget' => 'single_text',
                'format' => self::HTML5_FORMAT,
                'required' => false,
            ])

            ->add('zip', SubmitType::class, [
                'label' => 'Zip les posts',
            ]);
    }
}
