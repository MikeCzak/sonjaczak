<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Content\PageTexts;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * One page text (docs/planung/cms.md K2). Data: ['body' => string]. Option "placeholders": the placeholders of the
 * default text; any other %…% would appear literally on the page and is rejected.
 *
 * @extends AbstractType<array{body: ?string}>
 */
final class PageTextType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var list<string> $allowed */
        $allowed = $options['placeholders'];
        /** @var int $rows */
        $rows = $options['rows'];

        $builder->add('body', TextareaType::class, [
            'label' => 'page_text.field.body',
            // An empty text hides the section where the template allows it
            'required' => false,
            'empty_data' => '',
            'attr' => ['rows' => $rows],
            'constraints' => [
                new Callback(static function (mixed $value, ExecutionContextInterface $context) use ($allowed): void {
                    $unknown = array_diff(PageTexts::placeholders(\is_string($value) ? $value : ''), $allowed);
                    if ([] !== $unknown) {
                        $context->buildViolation('page_text.unknown_placeholder')
                            ->setParameter('%placeholders%', implode(', ', $unknown))
                            ->addViolation();
                    }
                }),
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'translation_domain' => 'admin',
            'placeholders' => [],
            'rows' => 12,
        ]);
        $resolver->setAllowedTypes('placeholders', 'string[]');
        $resolver->setAllowedTypes('rows', 'int');
    }
}
