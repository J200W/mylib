<?php

namespace App\Form;

use App\Entity\Author;
use App\Entity\Book;
use App\Entity\Category;
use App\Entity\Language;
use App\Repository\AuthorRepository;
use App\Repository\CategoryRepository;
use App\Repository\LanguageRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class BookType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = (bool) ($options['is_edit'] ?? false);

        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'attr' => [
                    'maxlength' => 255,
                    'class' => 'admin-input',
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Résumé / description',
                'required' => false,
                'attr' => [
                    'rows' => 5,
                    'class' => 'admin-textarea',
                ],
            ])
            ->add('stock', IntegerType::class, [
                'label' => 'Stock',
                'required' => false,
                'attr' => [
                    'min' => 0,
                    'class' => 'admin-input',
                ],
            ])
            ->add('author', EntityType::class, [
                'class' => Author::class,
                'label' => 'Auteur',
                'choice_label' => static fn (Author $author): string => trim($author->getFirstname().' '.$author->getLastname()),
                'placeholder' => 'Choisir un auteur',
                'attr' => [
                    'class' => 'admin-select',
                ],
                'query_builder' => static fn (AuthorRepository $repository) => $repository->createQueryBuilder('a')
                    ->orderBy('a.lastname', 'ASC')
                    ->addOrderBy('a.firstname', 'ASC'),
            ])
            ->add('category', EntityType::class, [
                'class' => Category::class,
                'label' => 'Catégories',
                'choice_label' => 'name',
                'multiple' => true,
                'expanded' => true,
                'by_reference' => false,
                'required' => false,
                'attr' => [
                    'class' => 'admin-category-checkboxes',
                ],
                'query_builder' => static fn (CategoryRepository $repository) => $repository->createQueryBuilder('c')
                    ->orderBy('c.name', 'ASC'),
            ])
            ->add('language', EntityType::class, [
                'class' => Language::class,
                'label' => 'Langue',
                'choice_label' => static fn (Language $language): string => $language->getCountry().' ('.mb_strtoupper((string) $language->getShortcode()).')',
                'placeholder' => 'Choisir une langue',
                'attr' => [
                    'class' => 'admin-select',
                ],
                'query_builder' => static fn (LanguageRepository $repository) => $repository->createQueryBuilder('l')
                    ->orderBy('l.country', 'ASC'),
            ])
            ->add('coverFile', FileType::class, [
                'label' => 'Couverture (image)',
                'mapped' => false,
                'required' => false,
                'help' => $isEdit ? 'Laisser vide pour conserver la couverture actuelle.' : null,
                'attr' => [
                    'class' => 'admin-input',
                    'accept' => 'image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp',
                ],
                'constraints' => [
                    new File(
                        maxSize: '5M',
                        mimeTypes: [
                            'image/jpeg',
                            'image/jpg',
                            'image/png',
                            'image/webp',
                        ],
                        mimeTypesMessage: 'Veuillez choisir une image au format JPEG, PNG ou WebP.',
                    ),
                ],
            ])
            ->add('save', SubmitType::class, [
                'label' => $isEdit ? 'Enregistrer les modifications' : 'Enregistrer le livre',
                'attr' => [
                    'class' => 'admin-btn admin-btn--primary',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Book::class,
            'is_edit' => false,
        ]);
        $resolver->setAllowedTypes('is_edit', 'bool');
    }
}
