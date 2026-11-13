<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Security\PasswordRules;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * New password, typed twice. Not mapped to the user: the controller hashes it and sets the hash.
 *
 * @extends AbstractType<array<string, mixed>>
 */
final class NewPasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('newPassword', RepeatedType::class, [
            'type' => PasswordType::class,
            'mapped' => false,
            'options' => ['attr' => ['autocomplete' => 'new-password']],
            'first_options' => [
                'label' => 'password.new',
                'help' => 'password.rules',
                'help_translation_parameters' => ['%min%' => PasswordRules::MIN_LENGTH],
                'constraints' => PasswordRules::constraints(),
            ],
            'second_options' => ['label' => 'password.repeat'],
            'invalid_message' => 'password.mismatch',
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['translation_domain' => 'admin']);
    }
}
