<?php

declare(strict_types=1);

namespace App\Form\Admin;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Changes the planned number of lessons of an enrollment (umsetzung-admin.md C7). Not mapped to the entity: the
 * lessons that would go are shown first, the entity only changes after Sonja confirmed.
 *
 * Three buttons: "save" (the number entered), "end" (the minimum, i.e. end the enrollment) and "confirm" (shown only
 * in the confirmation step, with the number already resolved).
 *
 * @extends AbstractType<array{plannedLessons: int|null, reason: string|null}>
 */
final class PlannedLessonsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var int $minimum checked in configureOptions() */
        $minimum = $options['minimum'];
        $builder
            ->add('plannedLessons', IntegerType::class, [
                'label' => 'enrollment.planned.field.count',
                'constraints' => [
                    new Assert\NotNull(groups: ['count']),
                    // Below the minimum, lessons that already took place would have to go (question 4)
                    new Assert\Range(
                        notInRangeMessage: 'enrollment.planned.out_of_range',
                        min: $minimum,
                        max: 40,
                        groups: ['count'],
                    ),
                ],
            ])
            ->add('reason', TextType::class, [
                'label' => 'enrollment.planned.field.reason',
                'help' => 'enrollment.planned.field.reason_help',
                'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 255)],
            ])
            // "end" ignores the number field, so only the reason is checked (group Default)
            ->add('save', SubmitType::class, ['label' => 'enrollment.planned.save', 'validation_groups' => ['Default', 'count']])
            ->add('end', SubmitType::class, ['label' => 'enrollment.planned.end', 'validation_groups' => ['Default'], 'attr' => ['class' => 'btn-outline-danger']])
            ->add('confirm', SubmitType::class, ['label' => 'enrollment.planned.confirm', 'validation_groups' => ['Default', 'count'], 'attr' => ['class' => 'btn-danger']]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['translation_domain' => 'admin']);
        $resolver->setRequired('minimum');
        $resolver->setAllowedTypes('minimum', 'int');
    }
}
