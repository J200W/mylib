<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Édition d’usager par l’admin (sans mot de passe obligatoire).
 */
final class AdminUserEditType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'E-mail',
                'attr' => ['class' => 'admin-form-input'],
                'label_attr' => ['class' => 'admin-form-label'],
            ])
            ->add('firstname', TextType::class, [
                'label' => 'Prénom',
                'attr' => ['class' => 'admin-form-input'],
                'label_attr' => ['class' => 'admin-form-label'],
            ])
            ->add('lastname', TextType::class, [
                'label' => 'Nom',
                'attr' => ['class' => 'admin-form-input'],
                'label_attr' => ['class' => 'admin-form-label'],
            ])
            ->add('plainPassword', PasswordType::class, [
                'label' => 'Nouveau mot de passe (optionnel)',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'class' => 'admin-form-input',
                    'autocomplete' => 'new-password',
                ],
                'label_attr' => ['class' => 'admin-form-label'],
                'help' => 'Laisser vide pour conserver le mot de passe actuel. Minimum 8 caractères, une lettre et un chiffre.',
            ])
            ->add('plainPasswordConfirm', PasswordType::class, [
                'label' => 'Confirmer le mot de passe',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'class' => 'admin-form-input',
                    'autocomplete' => 'new-password',
                ],
                'label_attr' => ['class' => 'admin-form-label'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
