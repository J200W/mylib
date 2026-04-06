<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'E-mail',
                'attr' => [
                    'class' => 'form-input',
                    'autocomplete' => 'email',
                ],
                'label_attr' => ['class' => 'form-label'],
            ])
            ->add('firstname', TextType::class, [
                'label' => 'Prénom',
                'attr' => [
                    'class' => 'form-input',
                    'autocomplete' => 'given-name',
                ],
                'label_attr' => ['class' => 'form-label'],
            ])
            ->add('lastname', TextType::class, [
                'label' => 'Nom',
                'attr' => [
                    'class' => 'form-input',
                    'autocomplete' => 'family-name',
                ],
                'label_attr' => ['class' => 'form-label'],
            ])
            ->add('password', PasswordType::class, [
                'label' => 'Mot de passe',
                'mapped' => false,
                'attr' => [
                    'class' => 'form-input',
                    'autocomplete' => 'new-password',
                ],
                'label_attr' => ['class' => 'form-label'],
                'constraints' => [
                    new Assert\NotBlank(message: 'Veuillez saisir un mot de passe.'),
                    new Assert\Length(
                        min: 8,
                        max: 4096,
                        minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.',
                    ),
                    new Assert\Regex(
                        pattern: '/\p{L}/u',
                        message: 'Le mot de passe doit contenir au moins une lettre.',
                    ),
                    new Assert\Regex(
                        pattern: '/\p{N}/u',
                        message: 'Le mot de passe doit contenir au moins un chiffre.',
                    ),
                ],
                'help' => 'Votre mot de passe doit contenir au moins 8 caractères, au moins une lettre et au moins un chiffre'
            ])
            ->add('confirm_password', PasswordType::class, [
                'label' => 'Confirmer le mot de passe',
                'mapped' => false,
                'attr' => [
                    'class' => 'form-input',
                    'autocomplete' => 'new-password',
                ],
                'label_attr' => ['class' => 'form-label'],
                'constraints' => [
                    new Assert\NotBlank(message: 'Veuillez confirmer le mot de passe.'),
                    new Assert\Length(
                        min: 8,
                        max: 4096,
                        minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.',
                    ),
                    new Assert\Regex(
                        pattern: '/\p{L}/u',
                        message: 'Le mot de passe doit contenir au moins une lettre.',
                    ),
                    new Assert\Regex(
                        pattern: '/\p{N}/u',
                        message: 'Le mot de passe doit contenir au moins un chiffre.',
                    ),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
