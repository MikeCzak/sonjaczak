<?php

declare(strict_types=1);

namespace App\Content;

use App\Entity\PageText;
use App\Entity\User;
use App\Repository\PageTextRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Translation\TranslatorBagInterface;

/**
 * Reading and changing the page texts in the admin area (docs/planung/cms.md K2).
 *
 * The defaults are the texts in translations/content.de.yaml (the translator's catalogue, PageTextTranslator leaves
 * it unchanged); a changed text is a row in page_text.
 */
final readonly class PageTexts
{
    private const string LOCALE = 'de';

    /**
     * How a template shows a text, so the editor can say what a line break does (docs/planung/cms.md K4):
     * one list item per line (text.list), a paragraph per blank line (text.paragraphs), every line kept (nl2br), or a
     * single line where a line break shows as a space. Must match the templates; PageTextKindTest checks it.
     */
    public const string KIND_LIST = 'list';
    public const string KIND_PARAGRAPHS = 'paragraphs';
    public const string KIND_LINES = 'lines';
    public const string KIND_LINE = 'line';

    /**
     * Texts shown with text.paragraphs (all other multi-line kinds follow from the last key segment).
     */
    private const array PARAGRAPHS = [
        'about.body',
        'lessons.rules.body',
        'lessons.faq.body',
        'privacy.hosting.body',
        'privacy.logs.body',
        'privacy.cookies.body',
        'privacy.fonts.body',
        'privacy.email.body',
        'privacy.links.body',
        'privacy.rights.body',
        'privacy_lessons.data.body',
        'privacy_lessons.purpose.body',
        'privacy_lessons.required.body',
        'privacy_lessons.recipients.body',
        'privacy_lessons.storage.body',
    ];

    /**
     * Texts whose section (or line) the template leaves out while they are empty.
     */
    private const array HIDDEN_WHEN_EMPTY = [
        'home.hero.claim',
        'home.intro.body',
        'booking_band.hero.claim',
        'lessons.rules.body',
        'lessons.faq.body',
    ];

    public function __construct(
        // No autowiring alias exists for this interface; "translator" is the service PageTextTranslator decorates
        #[Autowire(service: 'translator')]
        private TranslatorBagInterface $translator,
        private PageTextRepository $repository,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    /**
     * All page texts from the repository, in file order: translation key => default text.
     *
     * @return array<string, string>
     */
    public function defaults(): array
    {
        $defaults = [];
        foreach ($this->translator->getCatalogue(self::LOCALE)->all(PageTextTranslator::DOMAIN) as $key => $text) {
            $defaults[(string) $key] = \is_string($text) ? $text : '';
        }

        return $defaults;
    }

    public function exists(string $key): bool
    {
        return $this->translator->getCatalogue(self::LOCALE)->defines($key, PageTextTranslator::DOMAIN);
    }

    public function defaultText(string $key): string
    {
        return $this->translator->getCatalogue(self::LOCALE)->get($key, PageTextTranslator::DOMAIN);
    }

    /**
     * @return array<string, PageText>
     */
    public function changed(): array
    {
        return $this->repository->allByKey();
    }

    public function find(string $key): ?PageText
    {
        return $this->repository->findOneByKey($key);
    }

    public static function kind(string $key): string
    {
        $part = substr((string) strrchr('.'.$key, '.'), 1);

        return match (true) {
            \in_array($part, ['items', 'clauses'], true) => self::KIND_LIST,
            'address' === $part => self::KIND_LINES,
            \in_array($key, self::PARAGRAPHS, true) => self::KIND_PARAGRAPHS,
            default => self::KIND_LINE,
        };
    }

    public static function hiddenWhenEmpty(string $key): bool
    {
        return \in_array($key, self::HIDDEN_WHEN_EMPTY, true);
    }

    /**
     * Placeholders like %price% in a text, in order of appearance, without duplicates.
     *
     * @return list<string>
     */
    public static function placeholders(string $text): array
    {
        preg_match_all('/%[a-z_]+%/', $text, $matches);

        return array_values(array_unique($matches[0]));
    }

    /**
     * Line breaks from the browser (\r\n) become \n, so blank lines still separate paragraphs on the page.
     */
    public static function normalize(string $text): string
    {
        return str_replace(["\r\n", "\r"], "\n", $text);
    }

    /**
     * Stores a changed text. A text equal to the default removes the row, so it no longer counts as changed.
     */
    public function save(string $key, string $body, ?User $user): void
    {
        $body = self::normalize($body);
        $text = $this->find($key);

        if ($body === $this->defaultText($key)) {
            if (null !== $text) {
                $this->entityManager->remove($text);
                $this->entityManager->flush();
            }

            return;
        }

        if (null === $text) {
            $this->entityManager->persist(new PageText($key, $body, $this->clock->now(), $user));
        } else {
            $text->change($body, $this->clock->now(), $user);
        }
        $this->entityManager->flush();
    }

    /**
     * Back to the text from the repository.
     */
    public function reset(string $key): void
    {
        $text = $this->find($key);
        if (null !== $text) {
            $this->entityManager->remove($text);
            $this->entityManager->flush();
        }
    }
}
