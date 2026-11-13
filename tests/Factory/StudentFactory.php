<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\Embeddable\Address;
use App\Entity\Student;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * Invented students. Adults by default; minor() gives a child with a guardian and without own address.
 *
 * @extends PersistentObjectFactory<Student>
 */
final class StudentFactory extends PersistentObjectFactory
{
    #[\Override]
    public static function class(): string
    {
        return Student::class;
    }

    public function minor(): static
    {
        return $this->with(static fn (): array => [
            'birthDate' => \DateTimeImmutable::createFromMutable(self::faker()->dateTimeBetween('-17 years', '-8 years'))->setTime(0, 0),
            'email' => null,
            'address' => new Address(),
            'guardian' => GuardianFactory::new(),
        ]);
    }

    #[\Override]
    protected function defaults(): array
    {
        $faker = self::faker();

        return [
            'firstName' => $faker->firstName(),
            'lastName' => $faker->lastName(),
            'birthDate' => \DateTimeImmutable::createFromMutable($faker->dateTimeBetween('-60 years', '-19 years'))->setTime(0, 0),
            'email' => $faker->unique()->safeEmail(),
            'phone' => $faker->phoneNumber(),
            'address' => new Address($faker->streetAddress(), $faker->postcode(), $faker->city()),
        ];
    }
}
