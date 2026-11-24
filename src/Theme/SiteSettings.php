<?php

declare(strict_types=1);

namespace App\Theme;

use App\Entity\Settings;
use App\Repository\SettingsRepository;
use Doctrine\DBAL\Exception as DatabaseException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Settings of the public site (Twig global "site"): active theme, lesson price and home page slides.
 *
 * The theme comes from the settings in the database (App\Entity\Settings, changed by Sonja in the admin area).
 * The slides still come from config/services.yaml until the slide management (HomeSlide) is built.
 */
final readonly class SiteSettings
{
    /**
     * @param list<array{image: string, position: string}> $homeSlides
     */
    public function __construct(
        private SettingsRepository $settings,
        private LoggerInterface $logger,
        #[Autowire(param: 'app.home_slides')]
        private array $homeSlides,
    ) {
    }

    /**
     * The active theme, or a valid preview (?theme=<value>) for comparing themes. The preview only affects the
     * current page and stores nothing in the browser.
     *
     * If the database cannot be reached, the public pages still work, in the default theme.
     */
    public function theme(?string $preview = null): Theme
    {
        $previewed = Theme::tryFrom($preview ?? '');
        if (null !== $previewed) {
            return $previewed;
        }

        try {
            return $this->settings->current()->getTheme();
        } catch (DatabaseException $exception) {
            $this->logger->error('Settings not readable, default theme used: {message}', ['message' => $exception->getMessage()]);

            return Theme::Classic;
        }
    }

    /**
     * Placeholders for the texts on /unterricht (docs/planung/cms.md K3): price per term, lessons per term and the
     * price per lesson, from the settings. Defaults of App\Entity\Settings if the database cannot be reached.
     *
     * @return array{'%price%': string, '%lessons%': string, '%per_lesson%': string}
     */
    public function lessonParameters(): array
    {
        try {
            $settings = $this->settings->current();
        } catch (DatabaseException $exception) {
            $this->logger->error('Settings not readable, default lesson price used: {message}', ['message' => $exception->getMessage()]);
            $settings = new Settings();
        }

        $price = $settings->getLessonPriceCents();
        $lessons = $settings->getPlannedLessons();

        return [
            '%price%' => self::euros($price),
            '%lessons%' => (string) $lessons,
            '%per_lesson%' => self::euros((int) round($price / max(1, $lessons))),
        ];
    }

    /**
     * "€ 495,–" for whole euros, otherwise "€ 33,33" (Austrian style, as on the page since the first release).
     */
    public static function euros(int $cents): string
    {
        $euros = number_format(intdiv($cents, 100), 0, ',', '.');

        return 0 === $cents % 100 ? '€ '.$euros.',–' : '€ '.$euros.','.\sprintf('%02d', $cents % 100);
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
