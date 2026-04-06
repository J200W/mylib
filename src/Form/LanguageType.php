<?php

namespace App\Form;

use App\Entity\Language;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LanguageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = (bool) ($options['is_edit'] ?? false);

        $builder
            ->add('country', TextType::class, [
                'label' => 'Langue / pays',
                'attr' => [
                    'maxlength' => 255,
                    'class' => 'admin-input',
                ],
            ])
            ->add('shortcode', TextType::class, [
                'label' => 'Code (ex. fr, en)',
                'attr' => [
                    'maxlength' => 10,
                    'class' => 'admin-input',
                ],
            ])
            ->add('save', SubmitType::class, [
                'label' => $isEdit ? 'Enregistrer' : 'Enregistrer la langue',
                'attr' => [
                    'class' => 'admin-btn admin-btn--primary',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Language::class,
            'is_edit' => false,
        ]);
        $resolver->setAllowedTypes('is_edit', 'bool');
    }
}
