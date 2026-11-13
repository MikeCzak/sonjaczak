<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Entity\Embeddable\Address;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * The embeddable Address as a sub-form, used by StudentType and GuardianType (later also settings and invoices).
 *
 * @extends AbstractType<Address>
 */
final class AddressType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('street', TextType::class, ['label' => 'address.street', 'required' => false])
            ->add('postalCode', TextType::class, ['label' => 'address.postal_code', 'required' => false])
            ->add('city', TextType::class, ['label' => 'address.city', 'required' => false])
            ->add('country', TextType::class, [
                'label' => 'address.country',
                'help' => 'address.country_help',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Address::class,
            // The entities always hold an Address (Doctrine never leaves an embeddable null)
            'empty_data' => static fn (): Address => new Address(),
            'translation_domain' => 'admin',
        ]);
    }
}
