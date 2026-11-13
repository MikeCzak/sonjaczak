<?php

declare(strict_types=1);

namespace App\Form\Admin;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;

/**
 * "Mein Konto": change the own password. Asks for the current one, so a forgotten open session is not enough.
 *
 * @extends AbstractType<array<string, mixed>>
 */
final class ChangePasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('currentPassword', PasswordType::class, [
            'label' => 'password.current',
            'mapped' => false,
            'attr' => ['autocomplete' => 'current-password'],
            // Compares with the hash of the logged-in user
            'constraints' => [new UserPassword(message: 'password.current_wrong')],
            'priority' => 10,
        ]);
    }

    public function getParent(): string
    {
        return NewPasswordType::class;
    }
}
