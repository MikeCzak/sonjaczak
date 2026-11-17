<?php

declare(strict_types=1);

namespace App\Controller;

use App\Pdf\HtmlToPdf;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Pages without forms.
 */
final class PageController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function home(): Response
    {
        return $this->render('page/home.html.twig');
    }

    #[Route('/ueber-mich', name: 'app_about', methods: ['GET'])]
    public function about(): Response
    {
        return $this->render('page/about.html.twig');
    }

    #[Route('/datenschutz', name: 'app_privacy', methods: ['GET'])]
    public function privacy(): Response
    {
        return $this->render('page/privacy.html.twig');
    }

    /**
     * Information under Art. 13 GDPR for students and parents (umsetzung-admin.md F).
     */
    #[Route('/datenschutz/unterricht', name: 'app_privacy_lessons', methods: ['GET'])]
    public function privacyLessons(): Response
    {
        return $this->render('page/privacy_lessons.html.twig');
    }

    /**
     * The same notice as a PDF to hand out at registration (umsetzung-admin.md F2).
     *
     * Rendered once and kept in the app cache, because the page is public: dompdf needs about 0.1 s and 11 MB per PDF
     * (verlauf.md, D2b), which repeated calls would cost every time. The text only changes with a deploy, which starts
     * with an empty cache (new container). In debug mode rendered each time, so changes to the text show at once.
     */
    #[Route('/datenschutz/unterricht.pdf', name: 'app_privacy_lessons_pdf', methods: ['GET'])]
    public function privacyLessonsPdf(
        HtmlToPdf $htmlToPdf,
        CacheInterface $cache,
        #[Autowire('%kernel.debug%')] bool $debug,
    ): Response {
        $render = fn (): string => $htmlToPdf->convert($this->renderView('page/privacy_lessons_pdf.html.twig'));
        $pdf = $debug ? $render() : $cache->get('privacy_lessons_pdf', $render);

        return new Response($pdf, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            // Opens in the browser; saving keeps the name
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_INLINE, 'Datenschutz-Information_Gesangsunterricht.pdf'),
        ]);
    }
}
