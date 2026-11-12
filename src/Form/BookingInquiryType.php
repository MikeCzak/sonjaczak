<?php

declare(strict_types=1);

namespace App\Form;

use App\Inquiry\BookingInquiry;
use App\Inquiry\Occasion;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Inquiry for Sonja solo on /booking/solo.
 *
 * @extends AbstractType<BookingInquiry>
 */
final class BookingInquiryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('occasion', EnumType::class, [
                'class' => Occasion::class,
                'label' => 'form.label.occasion',
                'placeholder' => 'form.placeholder.choose',
                'choice_label' => static fn (Occasion $occasion): string => $occasion->labelKey(),
            ])
            // Native date picker; "min" only helps the browser, the constraint in BookingInquiry decides
            ->add('date', DateType::class, [
                'label' => 'form.label.date',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => ['min' => (new \DateTimeImmutable('today'))->format('Y-m-d')],
            ])
            ->add('place', TextType::class, [
                'label' => 'form.label.place',
                'help' => 'form.help.place',
            ])
            ->add('message', TextareaType::class, [
                'label' => 'form.label.message',
                'help' => 'form.help.booking_message',
                'attr' => ['rows' => 6],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => BookingInquiry::class]);
    }

    public function getParent(): string
    {
        return InquiryType::class;
    }
}
