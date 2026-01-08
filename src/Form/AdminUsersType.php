<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class AdminUsersType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('login', TextType::class, [
                'label' => 'Nom',
                'attr' => ['autocomplete' => 'username'],
                'required' => true,
            ])
            ->add('passwordOld', PasswordType::class, [
                'label' => 'Mot de passe',
                'attr' => ['autocomplete' => 'current-password'],
                'required' => true,
            ])

            ->add('password', RepeatedType::class, [
                'type' => PasswordType::class,
                'invalid_message' => 'Les mots de passe doivent correspondre',
                'first_options' => ['label' => 'Nouveau mot de passe', 'attr' => ['minlength' => 6, 'autocomplete' => 'new-password'],],
                'second_options' => ['label' => 'Répéter le mot de passe', 'attr' => ['minlength' => 6, 'autocomplete' => 'new-password'],],
                'required' => true,
            ])
            ->add('toggle', ButtonType::class, [
                'label' => '👁',
                'attr' => ['class' => 'togglePassword', 'title' => 'Voir les mots de passe', 'style' => 'margin-top: 1em;'],
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
                    ->add('changePassword', SubmitType::class, [
                        'label' => 'Changer le mot de passe',
                    ])
            );
    }
}
