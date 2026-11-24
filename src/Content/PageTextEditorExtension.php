<?php

declare(strict_types=1);

namespace App\Content;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Attribute\AsTwigFunction;
use Twig\Markup;

/**
 * Editing page texts where they are shown (docs/planung/cms.md K4).
 *
 * page_text_attr('lessons.audience.body') goes on the element that shows a text. For visitors it prints nothing; for
 * logged-in admins it prints data-page-text="…", which the Stimulus controller page_editor turns into an edit button.
 * The text itself still comes from |trans (PageTextTranslator); this only marks where it is.
 */
final readonly class PageTextEditorExtension
{
    public function __construct(private Security $security)
    {
    }

    /**
     * Whether the current visitor may edit page texts (ROLE_ADMIN, and so also ROLE_SUPER_ADMIN).
     */
    #[AsTwigFunction('page_text_editor')]
    public function editor(): bool
    {
        return $this->security->isGranted(User::ROLE_ADMIN);
    }

    #[AsTwigFunction('page_text_attr', isSafe: ['html'])]
    public function attribute(string $key): Markup
    {
        if (!$this->editor()) {
            return new Markup('', 'UTF-8');
        }

        return new Markup(' data-page-text="'.htmlspecialchars($key, \ENT_QUOTES | \ENT_HTML5, 'UTF-8').'"', 'UTF-8');
    }
}
