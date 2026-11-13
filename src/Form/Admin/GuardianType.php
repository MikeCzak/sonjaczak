<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Entity\Guardian;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<Guardian>
 */
final class GuardianType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // Non-nullable string: empty_data '' instead of null, NotBlank on the entity reports the empty field
            ->add('name', TextType::class, ['label' => 'guardian.field.name', 'help' => 'guardian.field.name_help', 'empty_data' => ''])
            ->add('email', EmailType::class, ['label' => 'person.email', 'required' => false])
            ->add('phone', TelType::class, ['label' => 'person.phone', 'required' => false])
            ->add('address', AddressType::class, ['label' => 'address.legend']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Guardian::class,
            'translation_domain' => 'admin',
        ]);
    }
}
