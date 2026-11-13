<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\Embeddable\Address;
use App\Entity\Guardian;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * Invented parents, e.g. "Anna und Peter Gruber" (one name field as on the invoice).
 *
 * @extends PersistentObjectFactory<Guardian>
 */
final class GuardianFactory extends PersistentObjectFactory
{
    #[\Override]
    public static function class(): string
    {
        return Guardian::class;
    }

    #[\Override]
    protected function defaults(): array
    {
        $faker = self::faker();

        return [
            'name' => $faker->firstNameFemale().' und '.$faker->firstNameMale().' '.$faker->lastName(),
            'email' => $faker->unique()->safeEmail(),
            'phone' => $faker->phoneNumber(),
            'address' => new Address($faker->streetAddress(), $faker->postcode(), $faker->city()),
        ];
    }
}
