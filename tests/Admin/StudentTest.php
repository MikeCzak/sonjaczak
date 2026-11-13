<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Entity\Student;
use App\Repository\StudentRepository;
use App\Tests\Factory\GuardianFactory;
use App\Tests\Factory\StudentFactory;
use App\Tests\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Students and guardians (step C1).
 */
final class StudentTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    public function testCreateStudentWithGuardianAndAddress(): void
    {
        $client = $this->loggedInClient();
        GuardianFactory::createOne(['name' => 'Anna und Peter Muster']);

        $client->request('GET', '/admin/schueler/neu');
        $client->submitForm('Speichern', [
            'student[firstName]' => ' Lena ',
            'student[lastName]' => 'Muster',
            'student[birthDate]' => '2012-03-04',
            'student[guardian]' => (string) GuardianFactory::first()->getId(),
            'student[address][street]' => 'Hauptplatz 1',
            'student[address][postalCode]' => '8010',
            'student[address][city]' => 'Graz',
        ]);
        self::assertResponseRedirects('/admin/schueler');

        $student = self::getContainer()->get(StudentRepository::class)->findOneBy(['lastName' => 'Muster']);
        self::assertInstanceOf(Student::class, $student);
        self::assertSame('Lena', $student->getFirstName());
        self::assertSame('Anna und Peter Muster', $student->getGuardian()?->getName());
        self::assertSame('Graz', $student->getAddress()->getCity());
        self::assertTrue($student->isMinorOn(new \DateTimeImmutable('2030-03-03')));
        self::assertFalse($student->isMinorOn(new \DateTimeImmutable('2030-03-04')));
    }

    public function testRequiredFieldsAreChecked(): void
    {
        $client = $this->loggedInClient();

        $client->request('GET', '/admin/schueler/neu');
        $client->submitForm('Speichern', ['student[firstName]' => '', 'student[lastName]' => '']);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorCount(2, '.invalid-feedback');
    }

    public function testSearchFindsByNameAndGuardian(): void
    {
        $client = $this->loggedInClient();
        StudentFactory::createOne(['firstName' => 'Lena', 'lastName' => 'Muster']);
        StudentFactory::createOne(['firstName' => 'Max', 'lastName' => 'Beispiel', 'guardian' => GuardianFactory::new(['name' => 'Eva Huber'])]);
        StudentFactory::createOne(['firstName' => 'Rabatt', 'lastName' => '100_Prozent']);

        $client->request('GET', '/admin/schueler?q=muster');
        self::assertSelectorCount(1, 'tbody tr');
        self::assertSelectorTextContains('tbody', 'Muster, Lena');

        $client->request('GET', '/admin/schueler?q=HUBER');
        self::assertSelectorTextContains('tbody', 'Beispiel, Max');

        // "_" is a LIKE wildcard; escaped, it only matches itself
        $client->request('GET', '/admin/schueler?q=0_P');
        self::assertSelectorCount(1, 'tbody tr');
        $client->request('GET', '/admin/schueler?q=_');
        self::assertSelectorCount(1, 'tbody tr');
    }

    public function testDeleteStudent(): void
    {
        $client = $this->loggedInClient();
        $student = StudentFactory::createOne();

        $client->request('GET', '/admin/schueler/'.$student->getId());
        $client->submitForm('Schüler:in löschen');

        self::assertResponseRedirects('/admin/schueler');
        StudentFactory::assert()->empty();
    }

    public function testGuardianWithStudentsCannotBeDeleted(): void
    {
        $client = $this->loggedInClient();
        $guardian = GuardianFactory::createOne();
        StudentFactory::createOne(['guardian' => $guardian]);

        // No delete button while students are assigned; the controller and the foreign key refuse it as well
        $client->request('GET', '/admin/elternteile/'.$guardian->getId());
        self::assertSelectorTextContains('main', 'Schüler:innen');
        self::assertSelectorNotExists('form[action$="/loeschen"]');

        $client->request('POST', '/admin/elternteile/'.$guardian->getId().'/loeschen');
        self::assertResponseRedirects();
        GuardianFactory::assert()->count(1);
    }

    private function loggedInClient(): KernelBrowser
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::new()->admin()->create(), 'admin');

        return $client;
    }
}
