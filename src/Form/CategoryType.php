<?php

namespace App\Form;

use App\Entity\Book;
use App\Entity\Category;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CategoryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $simple = (bool) ($options['admin_simple'] ?? false);
        $isEdit = (bool) ($options['is_edit'] ?? false);

        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom',
                'attr' => [
                    'maxlength' => 255,
                    'class' => 'admin-input',
                ],
            ]);

        if (!$simple) {
            $builder->add('books', EntityType::class, [
                'class' => Book::class,
                'choice_label' => 'id',
                'multiple' => true,
            ]);
        }

        $builder->add('save', SubmitType::class, [
            'label' => $isEdit ? 'Enregistrer' : 'Enregistrer la catégorie',
            'attr' => [
                'class' => 'admin-btn admin-btn--primary',
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Category::class,
            'admin_simple' => false,
            'is_edit' => false,
        ]);
        $resolver->setAllowedTypes('admin_simple', 'bool');
        $resolver->setAllowedTypes('is_edit', 'bool');
    }
}
