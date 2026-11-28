<?php

declare(strict_types=1);

namespace App\Inquiry;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Recognises typical sales pitches (SEO offers and the like) by words in any text field of an inquiry.
 *
 * Suspicious inquiries are only marked in the subject, not dropped, so a false positive never gets lost
 * (docs/planung/spam.md SP3).
 */
final readonly class SpamDetector
{
    private ?string $regex;

    /**
     * @param list<string> $phrases plain words or phrases, matched as whole words and case-insensitively
     */
    public function __construct(
        #[Autowire(param: 'app.inquiry_spam_phrases')]
        array $phrases,
    ) {
        // Spaces in a phrase match any whitespace, so a line break between "organic" and "traffic" still counts
        $alternatives = array_map(
            static fn (string $phrase): string => preg_replace('/ +/', '\s+', preg_quote(trim($phrase), '/')) ?? '',
            $phrases,
        );
        $this->regex = [] === $alternatives ? null : '/\b(?:'.implode('|', $alternatives).')\b/iu';
    }

    public function isSuspicious(Inquiry $inquiry): bool
    {
        if (null === $this->regex) {
            return false;
        }

        // All text fields (name, e-mail, message, place, …); enums and dates are skipped
        foreach (get_object_vars($inquiry) as $value) {
            if (\is_string($value) && 1 === preg_match($this->regex, $value)) {
                return true;
            }
        }

        return false;
    }
}
