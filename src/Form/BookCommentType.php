<?php

namespace App\Form;

use App\Entity\Comment;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class BookCommentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('text', TextareaType::class, [
                'label' => 'Votre avis',
                'attr' => [
                    'class' => 'book-comments__textarea',
                    'rows' => 5,
                    'placeholder' => 'Partagez votre ressenti sur ce livre…',
                ],
            ])
            ->add('rating', ChoiceType::class, [
                'label' => 'Note sur 5',
                'choices' => [
                    '5 — Excellent' => 5,
                    '4 — Très bien' => 4,
                    '3 — Bien' => 3,
                    '2 — Décevant' => 2,
                    '1 — Très décevant' => 1,
                ],
                'placeholder' => 'Choisir une note',
                'attr' => ['class' => 'book-comments__rating-select'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Comment::class,
        ]);
    }
}
