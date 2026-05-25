<?php

namespace App\Form;

use App\Entity\Apartment;
use App\Entity\Payment;
use App\Entity\Tenant;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;

class PaymentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('amount', MoneyType::class, [
                'label' => 'Amount',
                'currency' => 'PHP',
                'attr' => [
                    'class' => 'form-control',
                    'min' => '0',
                    'step' => '0.01'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Payment amount is required.']),
                    new GreaterThanOrEqual([
                        'value' => 0,
                        'message' => 'Payment amount cannot be negative.'
                    ])
                ]
            ])
            ->add('paymentDate', DateType::class, [
                'label' => 'Payment Date',
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('dueDate', DateType::class, [
                'label' => 'Due Date',
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Status',
                'choices' => [
                    'Pending' => 'pending',
                    'Paid' => 'paid',
                    'Overdue' => 'overdue',
                    'Cancelled' => 'cancelled'
                ],
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('paymentMethod', ChoiceType::class, [
                'label' => 'Payment Method',
                'choices' => [
                    'Cash' => 'cash',
                    'Check' => 'check',
                    'Bank Transfer' => 'bank_transfer',
                    'Credit Card' => 'credit_card',
                    'Online Payment' => 'online_payment',
                    'PayMongo' => 'paymongo',
                ],
                'required' => false,
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
                    'placeholder' => 'Enter payment notes'
                ]
            ])
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
            ->add('save', SubmitType::class, [
                'label' => 'Save Payment',
                'attr' => [
                    'class' => 'btn btn-primary'
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Payment::class,
        ]);
    }
}
