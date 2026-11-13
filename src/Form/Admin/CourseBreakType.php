<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Entity\CourseBreak;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A break entered by hand (holidays, school-specific days off). Without a last day it is a single day.
 *
 * @extends AbstractType<CourseBreak>
 */
final class CourseBreakType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $date = ['widget' => 'single_text', 'input' => 'datetime_immutable'];

        $builder
            ->add('label', TextType::class, ['label' => 'course_break.field.label', 'empty_data' => ''])
            ->add('firstDay', DateType::class, ['label' => 'course_break.field.first_day'] + $date)
            ->add('lastDay', DateType::class, ['label' => 'course_break.field.last_day', 'help' => 'course_break.field.last_day_help', 'required' => false] + $date)
            // SUBMIT runs before validation (which happens on POST_SUBMIT), so the constraints see the filled-in day
            ->addEventListener(FormEvents::SUBMIT, static function (FormEvent $event): void {
                $break = $event->getData();
                if ($break instanceof CourseBreak && null === $break->getLastDay()) {
                    $break->setLastDay($break->getFirstDay());
                }
            });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CourseBreak::class,
            'translation_domain' => 'admin',
        ]);
    }
}
