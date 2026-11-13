<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Invoice\InvoiceDraft;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<InvoiceDraft>
 */
final class InvoiceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $date = ['widget' => 'single_text', 'input' => 'datetime_immutable'];

        $builder
            ->add('description', TextType::class, ['label' => 'invoice.field.description', 'empty_data' => ''])
            ->add('issuedOn', DateType::class, ['label' => 'invoice.field.issued_on', 'help' => 'invoice.field.issued_on_help'] + $date)
            ->add('dueOn', DateType::class, ['label' => 'invoice.field.due_on'] + $date)
            ->add('secondInstallmentDueOn', DateType::class, [
                'label' => 'invoice.field.second_installment_due_on',
                'help' => 'invoice.field.second_installment_due_on_help',
                'required' => false,
            ] + $date);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => InvoiceDraft::class,
            'translation_domain' => 'admin',
            // The draft is created by the controller with the default description and today's date
            'empty_data' => null,
        ]);
    }
}
