<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Entity\Student;
use App\Entity\TrialLesson;
use App\Repository\StudentRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<TrialLesson>
 */
final class TrialLessonType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('student', EntityType::class, [
                'label' => 'trial.field.student',
                'help' => 'trial.field.student_help',
                'class' => Student::class,
                'query_builder' => static fn (StudentRepository $repository): QueryBuilder => $repository->createQueryBuilder('s')->orderBy('s.lastName')->addOrderBy('s.firstName'),
                'choice_label' => static fn (Student $student): string => $student->getLastName().', '.$student->getFirstName(),
                'choice_translation_domain' => false,
                'placeholder' => '',
            ])
            ->add('startsAt', DateTimeType::class, [
                'label' => 'lesson.field.starts_at',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('durationMinutes', IntegerType::class, ['label' => 'lesson.field.duration'])
            ->add('note', TextareaType::class, ['label' => 'lesson.field.note', 'required' => false, 'attr' => ['rows' => 2]]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TrialLesson::class,
            'translation_domain' => 'admin',
        ]);
    }
}
