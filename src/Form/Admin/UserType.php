<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Admin account in the user management (ROLE_SUPER_ADMIN only). No password field: the account holder sets it
 * through the invitation link.
 *
 * @extends AbstractType<User>
 */
final class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, ['label' => 'user.field.email'])
            // Not mapped: the entity stores a list of roles, the form offers exactly one admin role
            ->add('role', ChoiceType::class, [
                'label' => 'user.field.role',
                'mapped' => false,
                'choices' => [
                    'user.role.ROLE_ADMIN' => User::ROLE_ADMIN,
                    'user.role.ROLE_SUPER_ADMIN' => User::ROLE_SUPER_ADMIN,
                ],
                'data' => $options['role'],
                // The own role cannot be changed (App\Security\Voter\UserVoter)
                'disabled' => !$options['can_change_role'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'translation_domain' => 'admin',
            // Without this, Symfony would call new User() without the e-mail address the constructor needs
            'empty_data' => static fn (): User => new User(''),
            'role' => User::ROLE_ADMIN,
            'can_change_role' => true,
        ]);
        $resolver->setAllowedValues('role', [User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN]);
        $resolver->setAllowedTypes('can_change_role', 'bool');
    }
}
