<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Text field that people never see (rendered off-screen by the block "honeypot_row" in templates/form/theme.html.twig).
 * Bots fill in every field, so a value here marks the submission as spam.
 *
 * @extends AbstractType<string|null>
 */
final class HoneypotType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'required' => false,
            'label' => 'form.label.honeypot',
            'attr' => ['tabindex' => '-1', 'autocomplete' => 'off'],
        ]);
    }

    public function getParent(): string
    {
        return TextType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'honeypot';
    }
}
