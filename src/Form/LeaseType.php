<?php

namespace App\Form;

use App\Entity\Apartment;
use App\Entity\Lease;
use App\Entity\Tenant;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;

class LeaseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('tenant', EntityType::class, [
                'label' => 'Tenant',
                'class' => Tenant::class,
                'choice_label' => 'fullName',
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('apartment', EntityType::class, [
                'label' => 'Apartment',
                'class' => Apartment::class,
                'choice_label' => 'name',
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('startDate', DateType::class, [
                'label' => 'Start Date',
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('endDate', DateType::class, [
                'label' => 'End Date',
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('monthlyRent', MoneyType::class, [
                'label' => 'Monthly Rent',
                'currency' => 'PHP',
                'attr' => [
                    'class' => 'form-control',
                    'min' => '0',
                    'step' => '0.01'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Monthly rent is required.']),
                    new GreaterThanOrEqual([
                        'value' => 0,
                        'message' => 'Monthly rent cannot be negative. Please enter a positive value.'
                    ])
                ]
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Status',
                'choices' => [
                    'Active' => 'active',
                    'Inactive' => 'inactive',
                    'Expired' => 'expired',
                    'Terminated' => 'terminated'
                ],
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Enter lease notes'
                ]
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Save Lease',
                'attr' => [
                    'class' => 'btn btn-primary'
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Lease::class,
        ]);
    }
}





