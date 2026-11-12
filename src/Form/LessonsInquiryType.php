<?php

declare(strict_types=1);

namespace App\Form;

use App\Inquiry\LessonsInquiry;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Inquiry for singing lessons on /unterricht.
 *
 * The data-guardian-* attributes are used by assets/inquiry.js to show the guardian field only for students under 18.
 *
 * @extends AbstractType<LessonsInquiry>
 */
final class LessonsInquiryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('age', IntegerType::class, [
                'label' => 'form.label.age',
                'attr' => ['min' => 5, 'max' => 120, 'data-guardian-age' => LessonsInquiry::ADULT_AGE],
            ])
            // Not "required" in HTML: only for minors, which the server checks (LessonsInquiry::validateGuardian())
            ->add('guardian', TextType::class, [
                'label' => 'form.label.guardian',
                'label_attr' => ['class' => 'required'],
                'help' => 'form.help.guardian',
                'required' => false,
                'row_attr' => ['data-guardian-field' => ''],
            ])
            ->add('preferredTime', TextType::class, [
                'label' => 'form.label.preferred_time',
                'attr' => ['placeholder' => 'form.placeholder.preferred_time'],
            ])
            ->add('experience', TextareaType::class, [
                'label' => 'form.label.experience',
                'help' => 'form.help.experience',
                'required' => false,
                'attr' => ['rows' => 3],
            ])
            ->add('message', TextareaType::class, [
                'label' => 'form.label.message',
                'required' => false,
                'attr' => ['rows' => 4],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => LessonsInquiry::class]);
    }

    public function getParent(): string
    {
        return InquiryType::class;
    }
}
