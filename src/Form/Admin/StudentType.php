<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Entity\Guardian;
use App\Entity\Student;
use App\Repository\GuardianRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<Student>
 */
final class StudentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, ['label' => 'student.field.first_name', 'empty_data' => ''])
            ->add('lastName', TextType::class, ['label' => 'student.field.last_name', 'empty_data' => ''])
            ->add('birthDate', DateType::class, [
                'label' => 'student.field.birth_date',
                'help' => 'student.field.birth_date_help',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => false,
            ])
            ->add('email', EmailType::class, ['label' => 'person.email', 'required' => false])
            ->add('phone', TelType::class, ['label' => 'person.phone', 'required' => false])
            ->add('address', AddressType::class, ['label' => 'address.legend'])
            // A select over all guardians; fine for a few dozen. With many more, an autocomplete would be better.
            ->add('guardian', EntityType::class, [
                'label' => 'student.field.guardian',
                'help' => 'student.field.guardian_help',
                'class' => Guardian::class,
                'query_builder' => static fn (GuardianRepository $repository): QueryBuilder => $repository->createQueryBuilder('g')->orderBy('g.name'),
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => 'student.field.guardian_none',
            ])
            ->add('notes', TextareaType::class, ['label' => 'student.field.notes', 'required' => false, 'attr' => ['rows' => 4]]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Student::class,
            'translation_domain' => 'admin',
        ]);
    }
}
