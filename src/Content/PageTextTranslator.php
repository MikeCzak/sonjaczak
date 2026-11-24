<?php

declare(strict_types=1);

namespace App\Content;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DatabaseException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;
use Symfony\Component\Translation\Formatter\MessageFormatterInterface;
use Symfony\Component\Translation\MessageCatalogueInterface;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\Service\ResetInterface;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Page texts changed by Sonja (table page_text) take precedence over translations/content.de.yaml
 * (docs/planung/cms.md K1).
 *
 * Wraps Symfony's translator, so templates keep using 'key'|trans({}, 'content') and debug:translation still sees
 * the keys. Only the domain "content" in German is looked up; everything else goes straight to the inner translator.
 * getCatalogue() is not changed: it returns the texts from the repository, which the admin area shows as defaults.
 *
 * All changed texts are read with one query on the first page text of a request and kept until reset() (tag
 * kernel.reset, called after every request). Without the reset, a FrankenPHP worker would keep old texts.
 * Same interfaces as Symfony's DataCollectorTranslator, which decorates the translator the same way in dev.
 */
#[AsDecorator('translator')]
final class PageTextTranslator implements TranslatorInterface, TranslatorBagInterface, LocaleAwareInterface, WarmableInterface, ResetInterface
{
    public const string DOMAIN = 'content';

    /**
     * The only language with page texts (config/packages/translation.yaml).
     */
    private const string LOCALE = 'de';

    /**
     * @var array<string, string>|null null until loaded in this request
     */
    private ?array $texts = null;

    public function __construct(
        #[AutowireDecorated]
        private readonly TranslatorInterface&TranslatorBagInterface&LocaleAwareInterface $translator,
        // Private service of the FrameworkBundle (alias of translator.formatter.default)
        #[Autowire(service: 'translator.formatter')]
        private readonly MessageFormatterInterface $formatter,
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<mixed> $parameters
     */
    public function trans(?string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        $id = (string) $id;
        if (self::DOMAIN !== $domain || self::LOCALE !== ($locale ?? $this->translator->getLocale())) {
            return $this->translator->trans($id, $parameters, $domain, $locale);
        }

        $text = $this->texts()[$id] ?? null;
        if (null === $text) {
            return $this->translator->trans($id, $parameters, $domain, $locale);
        }

        // Same formatter as Symfony's Translator for a text from the YAML file: placeholders, plurals (%count%),
        // translatable parameters. Global parameters (framework.translator.globals) are not used in this project.
        return $this->formatter->format($text, self::LOCALE, $parameters);
    }

    public function reset(): void
    {
        $this->texts = null;
        if ($this->translator instanceof ResetInterface) {
            $this->translator->reset();
        }
    }

    public function getCatalogue(?string $locale = null): MessageCatalogueInterface
    {
        return $this->translator->getCatalogue($locale);
    }

    public function getCatalogues(): array
    {
        return $this->translator->getCatalogues();
    }

    public function setLocale(string $locale): void
    {
        $this->translator->setLocale($locale);
    }

    public function getLocale(): string
    {
        return $this->translator->getLocale();
    }

    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        return $this->translator instanceof WarmableInterface ? $this->translator->warmUp($cacheDir, $buildDir) : [];
    }

    /**
     * Passes on everything else the inner translator offers (e.g. getFallbackLocales() for debug:translation).
     *
     * @param array<mixed> $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->translator->{$method}(...$arguments);
    }

    /**
     * @return array<string, string>
     */
    private function texts(): array
    {
        if (null !== $this->texts) {
            return $this->texts;
        }

        try {
            $texts = [];
            foreach ($this->connection->fetchAllKeyValue('SELECT text_key, body FROM page_text') as $key => $body) {
                $texts[(string) $key] = \is_string($body) ? $body : '';
            }
        } catch (DatabaseException $exception) {
            // Like the theme (App\Theme\SiteSettings): the public pages still work, with the texts from the repository
            $this->logger->error('Page texts not readable, texts from the repository used: {message}', ['message' => $exception->getMessage()]);
            $texts = [];
        }

        return $this->texts = $texts;
    }
}
