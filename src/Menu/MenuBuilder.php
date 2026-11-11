<?php

declare(strict_types=1);

namespace App\Menu;

use Knp\Menu\Attribute\AsMenuBuilder;
use Knp\Menu\FactoryInterface;
use Knp\Menu\ItemInterface;

/**
 * Builds the main navigation. Labels are translation keys (domain "messages"), translated in menu/main.html.twig.
 */
final readonly class MenuBuilder
{
    public function __construct(
        private FactoryInterface $factory,
    ) {
    }

    #[AsMenuBuilder(name: 'main')]
    public function createMainMenu(): ItemInterface
    {
        $menu = $this->factory->createItem('root');

        $menu->addChild('nav.home', ['route' => 'app_home']);
        $menu->addChild('nav.about', ['route' => 'app_about']);

        $booking = $menu->addChild('nav.booking', ['route' => 'app_booking']);
        $booking->addChild('nav.booking_solo', ['route' => 'app_booking_solo']);
        $booking->addChild('nav.booking_band', ['route' => 'app_booking_band']);

        $menu->addChild('nav.lessons', ['route' => 'app_lessons']);
        $menu->addChild('nav.contact', ['route' => 'app_contact']);

        return $menu;
    }
}
