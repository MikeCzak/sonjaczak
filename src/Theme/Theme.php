<?php

declare(strict_types=1);

namespace App\Theme;

/**
 * Design themes of the public site. The value is set as data-theme on <html>; colors and fonts per theme live in
 * assets/styles/partials/themes/ (the default theme in _design.scss).
 *
 * Add a theme (e.g. a seasonal one): add a case here, a partial in themes/ with [data-theme='<value>'] and a
 * translation key theme.<value>. Sonja will switch the active theme in the admin area (later, see SiteSettings).
 */
enum Theme: string
{
    case Classic = 'classic';
    case Modern = 'modern';
    case Stage = 'stage';

    public function labelKey(): string
    {
        return 'theme.'.$this->value;
    }

    /**
     * Dark themes also switch Bootstrap's own components to dark mode (data-bs-theme).
     */
    public function isDark(): bool
    {
        return self::Stage === $this;
    }
}
