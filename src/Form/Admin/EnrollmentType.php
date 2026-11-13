<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Entity\Enrollment;
use App\Entity\Enum\Billing;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Enrollment of one student (chosen before, see EnrollmentController::new()). The weekly time slot can only be set
 * when creating: the lessons are generated from it, later changes are made per lesson.
 *
 * @extends AbstractType<Enrollment>
 */
final class EnrollmentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ($options['with_slot']) {
            $builder
                ->add('weekday', ChoiceType::class, [
                    'label' => 'enrollment.field.weekday',
                    // ISO numbers as in PHP's date('N'): 1 = Monday
                    'choices' => array_combine(array_map(static fn (int $day): string => 'weekday.'.$day, range(1, 7)), range(1, 7)),
                ])
                ->add('startTime', TimeType::class, [
                    'label' => 'enrollment.field.start_time',
                    'widget' => 'single_text',
                    'input' => 'datetime_immutable',
                ]);
        }

        $builder
            ->add('lessonMinutes', IntegerType::class, ['label' => 'enrollment.field.lesson_minutes'])
            ->add('priceCents', MoneyType::class, [
                'label' => 'enrollment.field.price',
                'help' => 'enrollment.field.price_help',
                'currency' => 'EUR',
                'divisor' => 100,
            ])
            ->add('billing', EnumType::class, [
                'label' => 'enrollment.field.billing',
                'class' => Billing::class,
                'choice_label' => static fn (Billing $billing): string => $billing->labelKey(),
            ])
            ->add('billToGuardian', CheckboxType::class, [
                'label' => 'enrollment.field.bill_to_guardian',
                'help' => 'enrollment.field.bill_to_guardian_help',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Enrollment::class,
            'translation_domain' => 'admin',
            'with_slot' => false,
        ]);
        $resolver->setAllowedTypes('with_slot', 'bool');
    }
}
