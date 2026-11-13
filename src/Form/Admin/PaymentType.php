<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Entity\Enum\PaymentMethod;
use App\Invoice\PaymentDraft;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<PaymentDraft>
 */
final class PaymentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('amountCents', MoneyType::class, ['label' => 'payment.field.amount', 'currency' => 'EUR', 'divisor' => 100])
            ->add('paidOn', DateType::class, ['label' => 'payment.field.paid_on', 'widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->add('method', EnumType::class, [
                'label' => 'payment.field.method',
                'class' => PaymentMethod::class,
                'choice_label' => static fn (PaymentMethod $method): string => $method->labelKey(),
                'expanded' => true,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PaymentDraft::class,
            'translation_domain' => 'admin',
            'empty_data' => null,
        ]);
    }
}
