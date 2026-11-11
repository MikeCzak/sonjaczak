<?php

declare(strict_types=1);

namespace App\Form;

use App\Inquiry\Inquiry;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Fields shared by all inquiry forms. The concrete forms use it as parent (getParent()) and add their own fields.
 *
 * @extends AbstractType<Inquiry>
 */
final class InquiryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'form.label.name',
                'attr' => ['autocomplete' => 'name'],
            ])
            ->add('email', EmailType::class, [
                'label' => 'form.label.email',
                'attr' => ['autocomplete' => 'email'],
            ])
            ->add('phone', TelType::class, [
                'label' => 'form.label.phone',
                'required' => false,
                'attr' => ['autocomplete' => 'tel'],
            ])
            ->add('website', HoneypotType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Inquiry::class]);
    }
}
