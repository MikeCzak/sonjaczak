<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Entity\Course;
use App\Entity\Enum\Term;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<Course>
 */
final class CourseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $date = ['widget' => 'single_text', 'input' => 'datetime_immutable'];

        $builder
            ->add('term', EnumType::class, [
                'label' => 'course.field.term',
                'class' => Term::class,
                'choice_label' => static fn (Term $term): string => 'course.term_choice.'.$term->value,
            ])
            ->add('year', IntegerType::class, ['label' => 'course.field.year', 'help' => 'course.field.year_help'])
            ->add('startsOn', DateType::class, ['label' => 'course.field.starts_on'] + $date)
            ->add('endsOn', DateType::class, ['label' => 'course.field.ends_on', 'help' => 'course.field.ends_on_help'] + $date)
            ->add('room', TextType::class, ['label' => 'course.field.room', 'required' => false])
            // Cents in the entity, euros in the form: divisor 100 converts both ways
            ->add('priceCents', MoneyType::class, [
                'label' => 'course.field.price',
                'help' => 'course.field.defaults_help',
                'currency' => 'EUR',
                'divisor' => 100,
            ])
            ->add('plannedLessons', IntegerType::class, ['label' => 'course.field.planned_lessons'])
            ->add('lessonMinutes', IntegerType::class, ['label' => 'course.field.lesson_minutes', 'help' => 'course.field.defaults_help']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Course::class,
            'translation_domain' => 'admin',
        ]);
    }
}
