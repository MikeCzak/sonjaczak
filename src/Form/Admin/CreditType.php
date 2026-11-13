<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Invoice\CreditDraft;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<CreditDraft>
 */
final class CreditType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('amountCents', MoneyType::class, ['label' => 'credit.field.amount', 'help' => 'credit.field.amount_help', 'currency' => 'EUR', 'divisor' => 100])
            ->add('description', TextType::class, ['label' => 'credit.field.description', 'empty_data' => ''])
            ->add('issuedOn', DateType::class, ['label' => 'invoice.field.issued_on', 'widget' => 'single_text', 'input' => 'datetime_immutable']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CreditDraft::class,
            'translation_domain' => 'admin',
            'empty_data' => null,
        ]);
    }
}
