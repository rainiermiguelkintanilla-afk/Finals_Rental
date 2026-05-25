<?php

namespace App\Form;

use App\Entity\Apartment;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;

class ApartmentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Apartment Name',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Enter apartment name'
                ]
            ])
            ->add('address', TextType::class, [
                'label' => 'Address',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Enter full address'
                ]
            ])
            ->add('bedrooms', IntegerType::class, [
                'label' => 'Bedrooms',
                'attr' => [
                    'class' => 'form-control',
                    'min' => 1,
                    'max' => 10
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Number of bedrooms is required.']),
                    new GreaterThanOrEqual([
                        'value' => 1,
                        'message' => 'Number of bedrooms must be at least 1.'
                    ])
                ]
            ])
            ->add('bathrooms', IntegerType::class, [
                'label' => 'Bathrooms',
                'attr' => [
                    'class' => 'form-control',
                    'min' => 1,
                    'max' => 10
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Number of bathrooms is required.']),
                    new GreaterThanOrEqual([
                        'value' => 1,
                        'message' => 'Number of bathrooms must be at least 1.'
                    ])
                ]
            ])
            ->add('rentAmount', MoneyType::class, [
                'label' => 'Rent Amount',
                'currency' => 'PHP',
                'attr' => [
                    'class' => 'form-control',
                    'min' => '0',
                    'step' => '0.01'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Rent amount is required.']),
                    new GreaterThanOrEqual([
                        'value' => 0,
                        'message' => 'Rent amount cannot be negative.'
                    ])
                ]
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Status',
                'choices' => [
                    'Available' => 'available',
                    'Occupied' => 'occupied',
                    'Maintenance' => 'maintenance',
                    'Unavailable' => 'unavailable'
                ],
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                    'placeholder' => 'Enter apartment description'
                ]
            ])
            ->add('image', FileType::class, [
                'label' => 'Apartment Image',
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'form-control',
                    'accept' => 'image/*'
                ]
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Save Apartment',
                'attr' => [
                    'class' => 'btn btn-primary'
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Apartment::class,
        ]);
    }
}
