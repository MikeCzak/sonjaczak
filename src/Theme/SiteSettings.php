<?php

declare(strict_types=1);

namespace App\Theme;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Settings of the public site that Sonja will change in the admin area: active theme and home page slides.
 *
 * For now they come from parameters in config/services.yaml. With the admin area this class reads them from the
 * database instead; templates (Twig global "site") stay the same.
 */
final readonly class SiteSettings
{
    /**
     * @param list<array{image: string, position: string}> $homeSlides
     */
    public function __construct(
        #[Autowire(param: 'app.theme')]
        private string $theme,
        #[Autowire(param: 'app.home_slides')]
        private array $homeSlides,
    ) {
    }

    /**
     * The active theme, or a valid preview (?theme=<value>) for comparing themes. The preview only affects the
     * current page and stores nothing in the browser.
     */
    public function theme(?string $preview = null): Theme
    {
        return Theme::tryFrom($preview ?? '') ?? Theme::from($this->theme);
    }

    /**
     * Slides of the home page hero: image name (config/packages/images.yaml) and the point that stays visible when
     * the image is cropped (CSS object-position).
     *
     * @return list<array{image: string, position: string}>
     */
    public function homeSlides(): array
    {
        return $this->homeSlides;
    }
}
