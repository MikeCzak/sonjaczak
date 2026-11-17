<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Entity\Course;
use App\Entity\Embeddable\Address;
use App\Entity\Enrollment;
use App\Entity\Invoice;
use App\Entity\Settings;
use App\Invoice\CreditDraft;
use App\Invoice\InvoiceDraft;
use App\Invoice\InvoiceIssuer;
use App\Tests\Factory\CourseFactory;
use App\Tests\Factory\EnrollmentFactory;
use App\Tests\Factory\InvoiceFactory;
use App\Tests\Factory\StudentFactory;
use App\Tests\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

use function Zenstruck\Foundry\Persistence\persist;

/**
 * Download of the invoice PDFs, one at a time and as a ZIP per course (step D2d).
 */
final class InvoiceDownloadTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    public function testDownloadWithFileNameFromTheScheme(): void
    {
        $client = $this->loggedInClient();
        $invoice = $this->issue($this->enrollment(CourseFactory::createOne(), 'Müßig'));

        $client->request('GET', '/admin/honorarnoten/'.$invoice->getId().'/pdf');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/pdf');
        // Umlauts in filename* (percent-encoded UTF-8), ASCII fallback in filename
        self::assertResponseHeaderSame('Content-Disposition', "attachment; filename=HN1_GU_WiSe_2026_Muessig.pdf; filename*=utf-8''HN1_GU_WiSe_2026_M%C3%BC%C3%9Fig.pdf");
        self::assertStringStartsWith('%PDF-', (string) $client->getResponse()->getContent());
    }

    public function testFileNames(): void
    {
        self::bootKernel();
        $this->completeSettings();
        $invoice = $this->issue($this->enrollment(CourseFactory::createOne(['year' => 2027]), "van der Berg/O'Neil"));
        $draft = new CreditDraft('Gutschrift', new \DateTimeImmutable('2026-11-20'), $invoice->getCreditableCents());
        $draft->amountCents = 1000;
        $note = self::getContainer()->get(InvoiceIssuer::class)->credit($invoice, $draft);

        self::assertSame('HN1_GU_WiSe_2027_van-der-Berg-O-Neil.pdf', $invoice->getFileName());
        self::assertSame('HN2_GU_WiSe_2027_van-der-Berg-O-Neil_Gutschrift.pdf', $note->getFileName());
    }

    public function testInvoiceWithoutPdfIsNotFound(): void
    {
        $client = $this->loggedInClient();
        // Created by the factory, not by the issuer: no PDF, like an invoice from before step D2
        $invoice = InvoiceFactory::createOne();

        $client->request('GET', '/admin/honorarnoten/'.$invoice->getId().'/pdf');
        self::assertResponseStatusCodeSame(404);
    }

    /**
     * The ZIP contains invoices and credit notes of the course, nothing from other courses. The course page links to
     * it and counts the enrollments without an invoice.
     */
    public function testZipPerCourse(): void
    {
        $client = $this->loggedInClient();
        $course = CourseFactory::createOne();
        $invoice = $this->issue($this->enrollment($course, 'Springer'));
        $this->enrollment($course, 'Ohnenote');
        $this->issue($this->enrollment(CourseFactory::createOne(['year' => 2027]), 'Anderer'));
        $draft = new CreditDraft('Gutschrift', new \DateTimeImmutable('2026-11-20'), $invoice->getCreditableCents());
        $draft->amountCents = 1000;
        self::getContainer()->get(InvoiceIssuer::class)->credit($invoice, $draft);

        $client->request('GET', '/admin/kurse/'.$course->getId());
        self::assertSelectorExists('a[href="/admin/kurse/'.$course->getId().'/honorarnoten.zip"]');
        self::assertSelectorTextContains('main', '1 Teilnahme hat noch keine Honorarnote.');

        $client->request('GET', '/admin/kurse/'.$course->getId().'/honorarnoten.zip');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/zip');
        self::assertStringContainsString('filename=Honorarnoten_GU_WiSe_2026.zip', (string) $client->getResponse()->headers->get('Content-Disposition'));

        self::assertSame(['HN1_GU_WiSe_2026_Springer.pdf', 'HN3_GU_WiSe_2026_Springer_Gutschrift.pdf'], $this->zipEntries((string) $client->getResponse()->getContent()));
    }

    public function testZipOfCourseWithoutInvoicesIsNotFound(): void
    {
        $client = $this->loggedInClient();
        $course = CourseFactory::createOne();

        $client->request('GET', '/admin/kurse/'.$course->getId().'/honorarnoten.zip');
        self::assertResponseStatusCodeSame(404);
    }

    public function testAccountWithoutAdminRoleCannotDownload(): void
    {
        $client = self::createClient();
        $this->completeSettings();
        $invoice = $this->issue($this->enrollment(CourseFactory::createOne(), 'Springer'));
        $courseId = $invoice->getEnrollment()->getCourse()->getId();

        // Anonymous visitors: covered for every /admin route by AdminRoutesRequireLoginTest
        $client->loginUser(UserFactory::createOne(), 'admin');
        $client->request('GET', '/admin/honorarnoten/'.$invoice->getId().'/pdf');
        self::assertResponseStatusCodeSame(403);
        $client->request('GET', '/admin/kurse/'.$courseId.'/honorarnoten.zip');
        self::assertResponseStatusCodeSame(403);
    }

    /**
     * @return list<string>
     */
    private function zipEntries(string $content): array
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($path, $content);
        $zip = new \ZipArchive();
        self::assertTrue($zip->open($path));
        $names = [];
        for ($i = 0; $i < $zip->numFiles; ++$i) {
            $names[] = (string) $zip->getNameIndex($i);
            self::assertStringStartsWith('%PDF-', (string) $zip->getFromIndex($i));
        }
        $zip->close();
        unlink($path);

        return $names;
    }

    private function issue(Enrollment $enrollment): Invoice
    {
        $draft = new InvoiceDraft('Gesangsunterricht im Wintersemester 2026/27', new \DateTimeImmutable('2026-11-07'));
        $draft->dueOn = new \DateTimeImmutable('2026-11-13');

        return self::getContainer()->get(InvoiceIssuer::class)->issue($enrollment, $draft);
    }

    private function loggedInClient(): KernelBrowser
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::new()->admin()->create(), 'admin');
        $this->completeSettings();

        return $client;
    }

    private function completeSettings(): void
    {
        persist(Settings::class, ['senderName' => 'Erika Musterfrau', 'senderAddress' => new Address('Musterstraße 1', '8010', 'Graz'), 'iban' => 'AT611904300234573201']);
    }

    private function enrollment(Course $course, string $lastName): Enrollment
    {
        $student = StudentFactory::createOne(['lastName' => $lastName, 'guardian' => null, 'address' => new Address('Musterweg 7', '8020', 'Graz')]);

        return EnrollmentFactory::createOne(['course' => $course, 'student' => $student, 'billToGuardian' => false, 'priceCents' => 49500]);
    }
}
