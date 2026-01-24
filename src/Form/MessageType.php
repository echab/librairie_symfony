<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Message;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class MessageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Votre nom et prénom (obligatoire):',
                'attr' => ['size' => 40, 'autocomplete' => 'name'],
                'constraints' => new Assert\NotBlank,
            ])
            ->add('telephone', TelType::class, [
                'label' => 'Votre numéro de téléphone (obligatoire):',
                'attr' => ['size' => 15],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Votre email (obligatoire):',
                'attr' => ['size' => 40],
            ])
            ->add('sujet', TextType::class, [
                'label' => 'Sujet:',
                'attr' => ['size' => 40, 'autocomplete' => 'off'],
                'required' => false,
            ])
            ->add('message', TextareaType::class, [
                'label' => 'Votre message:',
                'attr' => ['rows' => 15, 'cols' => 80, 'autocomplete' => 'off'],
            ])
            ->add('captcha', CaptchaType::class, [
                'label' => 'Je ne suis pas un robot:',
                'attr' => ['autocomplete' => 'one-time-code'],
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
                    ->add('envoyer', SubmitType::class, [
                        'label' => 'Envoyer',
                    ])
            );
    }
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Message::class,
        ]);
    }
}
