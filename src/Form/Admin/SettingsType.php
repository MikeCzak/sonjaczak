<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Entity\Settings;
use App\Theme\Theme;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<Settings>
 */
final class SettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $optional = ['required' => false];

        $builder
            ->add('theme', EnumType::class, [
                'label' => 'settings.field.theme',
                'help' => 'settings.field.theme_help',
                'class' => Theme::class,
                'choice_label' => static fn (Theme $theme): string => $theme->labelKey(),
                // Labels live in the public site's domain, next to the theme preview
                'choice_translation_domain' => 'messages',
            ])
            ->add('lessonMinutes', IntegerType::class, ['label' => 'settings.field.lesson_minutes'])
            ->add('makeUpThreshold', IntegerType::class, [
                'label' => 'settings.field.make_up_threshold',
                'help' => 'settings.field.make_up_threshold_help',
            ])
            ->add('senderName', TextType::class, ['label' => 'settings.field.sender_name'] + $optional)
            ->add('senderTitle', TextType::class, ['label' => 'settings.field.sender_title'] + $optional)
            ->add('senderAddress', AddressType::class, ['label' => 'address.legend'])
            ->add('iban', TextType::class, ['label' => 'settings.field.iban'] + $optional)
            ->add('bic', TextType::class, ['label' => 'settings.field.bic'] + $optional)
            ->add('bankName', TextType::class, ['label' => 'settings.field.bank_name'] + $optional)
            ->add('invoicePlace', TextType::class, ['label' => 'settings.field.invoice_place', 'help' => 'settings.field.invoice_place_help'] + $optional)
            ->add('vatNote', TextareaType::class, ['label' => 'settings.field.vat_note', 'attr' => ['rows' => 3]] + $optional);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Settings::class,
            'translation_domain' => 'admin',
        ]);
    }
}
