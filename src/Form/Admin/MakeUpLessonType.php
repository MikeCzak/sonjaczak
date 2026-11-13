<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Entity\Lesson;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotNull;

/**
 * A make-up lesson for a lesson Sonja cancelled. The choices come from the controller (cancelled lessons of this
 * enrollment that have no make-up lesson yet).
 *
 * @extends AbstractType<Lesson>
 */
final class MakeUpLessonType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('makeUpFor', EntityType::class, [
                'label' => 'lesson.field.make_up_for',
                'class' => Lesson::class,
                'choices' => $options['cancelled_lessons'],
                'choice_label' => static fn (Lesson $lesson): string => (string) $lesson->getStartsAt()?->format('d.m.Y'),
                'constraints' => [new NotNull()],
                // Dates, nothing to translate
                'choice_translation_domain' => false,
            ])
            ->add('startsAt', DateTimeType::class, [
                'label' => 'lesson.field.starts_at',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('durationMinutes', IntegerType::class, ['label' => 'lesson.field.duration']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Lesson::class,
            'translation_domain' => 'admin',
        ]);
        $resolver->setRequired('cancelled_lessons');
        $resolver->setAllowedTypes('cancelled_lessons', 'array');
    }
}
