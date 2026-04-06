<?php

namespace App\Form;

use App\Entity\Reservation;
use App\Service\ReservationDateParser;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ReservationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $minStart = $options['min_date_start'];
        \assert($minStart instanceof \DateTimeInterface);

        $builder
            ->add('date_start', DateType::class, [
                'label' => 'Date de début',
                'widget' => 'single_text',
                'html5' => true,
                'input' => 'datetime',
                'attr' => [
                    'min' => $minStart->format('Y-m-d'),
                ],
            ])
            ->add('date_end', DateType::class, [
                'label' => 'Date de fin',
                'widget' => 'single_text',
                'html5' => true,
                'input' => 'datetime',
            ]);

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $reservation = $event->getData();
            if (!$reservation instanceof Reservation) {
                return;
            }
            $start = $reservation->getDateStart();
            $end = $reservation->getDateEnd();
            if (!$start instanceof \DateTimeInterface || !$end instanceof \DateTimeInterface) {
                return;
            }
            $parsed = ReservationDateParser::parse(
                $start->format('Y-m-d'),
                $end->format('Y-m-d'),
            );
            if (isset($parsed['error'])) {
                $event->getForm()->addError(new FormError($parsed['error']));
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Reservation::class,
            'min_date_start' => new \DateTime('today'),
        ]);
        $resolver->setAllowedTypes('min_date_start', [\DateTimeInterface::class]);
    }
}
