<?php

namespace App\Form;

use App\Entity\ClientRentals;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ClientRentalsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('clientName', TextType::class, [
                'label' => 'Client Name',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Enter client name'
                ]
            ])
            ->add('apartment', TextType::class, [
                'label' => 'Apartment',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Enter apartment name/number'
                ]
            ])
            ->add('checkInDate', DateType::class, [
                'label' => 'Check-in Date',
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('checkOutDate', DateType::class, [
                'label' => 'Check-out Date',
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('guests', IntegerType::class, [
                'label' => 'Number of Guests',
                'attr' => [
                    'class' => 'form-control',
                    'min' => 1,
                    'max' => 20
                ]
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Status',
                'choices' => [
                    'Pending' => 'pending',
                    'Confirmed' => 'confirmed',
                    'Cancelled' => 'cancelled',
                    'Completed' => 'completed'
                ],
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Save Booking',
                'attr' => [
                    'class' => 'btn btn-primary'
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ClientRentals::class,
        ]);
    }
}

