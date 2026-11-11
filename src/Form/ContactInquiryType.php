<?php

declare(strict_types=1);

namespace App\Form;

use App\Inquiry\ContactInquiry;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * General inquiry on the contact page.
 *
 * @extends AbstractType<ContactInquiry>
 */
final class ContactInquiryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('message', TextareaType::class, [
            'label' => 'form.label.message',
            'attr' => ['rows' => 6],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ContactInquiry::class]);
    }

    public function getParent(): string
    {
        return InquiryType::class;
    }
}
