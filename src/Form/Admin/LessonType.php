<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Entity\Enum\LessonStatus;
use App\Entity\Lesson;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * One lesson: status and note, or moved to another time. Until the course grid (step C4) this is the way to mark
 * lessons.
 *
 * @extends AbstractType<Lesson>
 */
final class LessonType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('status', EnumType::class, [
                'label' => 'lesson.field.status',
                'class' => LessonStatus::class,
                'choice_label' => static fn (LessonStatus $status): string => $status->labelKey(),
                'expanded' => true,
            ])
            ->add('note', TextareaType::class, [
                'label' => 'lesson.field.note',
                'help' => 'lesson.field.note_help',
                'required' => false,
                'attr' => ['rows' => 2],
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
    }
}
