<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Content\PageTexts;
use App\Entity\User;
use App\Form\Admin\PageTextType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Page texts of the public site (docs/planung/cms.md): list by page, edit, back to the text from the repository (K2),
 * and the JSON endpoints for editing in place on the public pages (K4, assets/controllers/page_editor_controller.js).
 *
 * CSRF: one session token "page-text" (csrf_token('page-text')), as a form field on the edit page and in the header
 * X-CSRF-Token for the JSON endpoints (erklaerungen/17-csrf.md).
 */
#[IsGranted(User::ROLE_ADMIN)]
#[Route('/admin/texte')]
final class PageTextController extends AbstractController
{
    /**
     * Translation keys of page texts: lowercase segments separated by dots.
     */
    private const string KEY = '[a-z0-9_]+(\.[a-z0-9_]+)+';

    public const string TOKEN_ID = 'page-text';

    public function __construct(
        private readonly PageTexts $texts,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: 'admin_page_text_index', methods: ['GET'])]
    public function index(): Response
    {
        $defaults = $this->texts->defaults();
        $changed = $this->texts->changed();

        // Grouped by page (first segment of the key), in the order of content.de.yaml
        $pages = [];
        foreach ($defaults as $key => $default) {
            $pages[explode('.', $key, 2)[0]][$key] = [
                'text' => isset($changed[$key]) ? $changed[$key]->getBody() : $default,
                'changed' => $changed[$key] ?? null,
                'label' => $this->label($key, $defaults, $changed),
            ];
        }

        return $this->render('admin/page_text/index.html.twig', ['pages' => $pages]);
    }

    #[Route('/{key}', name: 'admin_page_text_edit', requirements: ['key' => self::KEY], methods: ['GET', 'POST'])]
    public function edit(Request $request, string $key): Response
    {
        if (!$this->texts->exists($key)) {
            throw $this->createNotFoundException();
        }

        $default = $this->texts->defaultText($key);
        $changed = $this->texts->find($key);
        $placeholders = PageTexts::placeholders($default);

        $form = $this->createForm(PageTextType::class, ['body' => $changed?->getBody() ?? $default], [
            'placeholders' => $placeholders,
            // A single line needs no tall field
            'rows' => PageTexts::KIND_LINE === PageTexts::kind($key) ? 3 : 12,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{body: string} $data */
            $data = $form->getData();
            $user = $this->getUser();
            $this->texts->save($key, $data['body'], $user instanceof User ? $user : null);
            $this->addFlash('success', 'page_text.flash.saved');

            return $this->redirectToRoute('admin_page_text_index', ['_fragment' => explode('.', $key, 2)[0]]);
        }

        $defaults = $this->texts->defaults();

        return $this->render('admin/page_text/edit.html.twig', [
            'form' => $form,
            'key' => $key,
            'page' => explode('.', $key, 2)[0],
            'label' => $this->label($key, $defaults, $this->texts->changed()),
            'default' => $default,
            'changed' => $changed,
            'placeholders' => $placeholders,
            'kind' => PageTexts::kind($key),
            'hint' => $this->hint($key),
        ]);
    }

    #[Route('/{key}/zuruecksetzen', name: 'admin_page_text_reset', requirements: ['key' => self::KEY], methods: ['POST'])]
    public function reset(Request $request, string $key): Response
    {
        if ($this->isCsrfTokenValid(self::TOKEN_ID, $request->getPayload()->getString('_token'))) {
            $this->texts->reset($key);
            $this->addFlash('success', 'page_text.flash.reset');
        }

        return $this->redirectToRoute('admin_page_text_index', ['_fragment' => explode('.', $key, 2)[0]]);
    }

    /**
     * Everything the editor on a public page needs: the raw text (placeholders like %price% not replaced), the text
     * from the repository, whether and when it was changed, the allowed placeholders.
     */
    #[Route('/{key}/daten', name: 'admin_page_text_data', requirements: ['key' => self::KEY], methods: ['GET'])]
    public function data(string $key): JsonResponse
    {
        if (!$this->texts->exists($key)) {
            return $this->json(['error' => $this->translator->trans('page_text.editor.not_found', [], 'admin')], Response::HTTP_NOT_FOUND);
        }

        $default = $this->texts->defaultText($key);
        $changed = $this->texts->find($key);

        return $this->json([
            'key' => $key,
            'label' => $this->labelText($key),
            'text' => $changed?->getBody() ?? $default,
            'default' => $default,
            'changed' => null === $changed ? null : $this->translator->trans('page_text.changed', [
                '%date%' => $changed->getUpdatedAt()->format('d.m.Y H:i'),
                '%user%' => $changed->getUpdatedBy()?->getEmail() ?? $this->translator->trans('page_text.deleted_user', [], 'admin'),
            ], 'admin'),
            'placeholders' => PageTexts::placeholders($default),
            'kind' => PageTexts::kind($key),
            'hint' => $this->hint($key),
        ]);
    }

    /**
     * What a line break does in this text, and whether leaving it empty hides its section.
     */
    private function hint(string $key): string
    {
        $hint = $this->translator->trans('page_text.kind.'.PageTexts::kind($key), [], 'admin');
        if (PageTexts::hiddenWhenEmpty($key)) {
            $hint .= ' '.$this->translator->trans('page_text.kind.hidden_when_empty', [], 'admin');
        }

        return $hint;
    }

    /**
     * Saves a text sent as JSON {"text": "…"}. Same rules as the form (PageTextType), so the form validates it.
     */
    #[Route('/{key}/speichern', name: 'admin_page_text_save', requirements: ['key' => self::KEY], methods: ['POST'])]
    public function save(Request $request, string $key): JsonResponse
    {
        if (!$this->isCsrfTokenValid(self::TOKEN_ID, (string) $request->headers->get('X-CSRF-Token'))) {
            return $this->json(['errors' => [$this->translator->trans('page_text.editor.expired', [], 'admin')]], Response::HTTP_FORBIDDEN);
        }
        if (!$this->texts->exists($key)) {
            return $this->json(['errors' => [$this->translator->trans('page_text.editor.not_found', [], 'admin')]], Response::HTTP_NOT_FOUND);
        }

        $text = $request->getPayload()->get('text');
        // The token is checked above; the form only validates
        $form = $this->createForm(PageTextType::class, null, [
            'placeholders' => PageTexts::placeholders($this->texts->defaultText($key)),
            'csrf_protection' => false,
        ]);
        $form->submit(['body' => \is_string($text) ? $text : '']);

        if (!$form->isValid()) {
            $errors = [];
            foreach ($form->getErrors(true) as $error) {
                $errors[] = $error->getMessage();
            }

            return $this->json(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        /** @var array{body: string} $data */
        $data = $form->getData();
        $user = $this->getUser();
        $this->texts->save($key, $data['body'], $user instanceof User ? $user : null);

        return $this->json(['saved' => true]);
    }

    /**
     * Back to the text from the repository, from the editor on a public page.
     */
    #[Route('/{key}/zuruecksetzen.json', name: 'admin_page_text_reset_json', requirements: ['key' => self::KEY], methods: ['POST'])]
    public function resetJson(Request $request, string $key): JsonResponse
    {
        if (!$this->isCsrfTokenValid(self::TOKEN_ID, (string) $request->headers->get('X-CSRF-Token'))) {
            return $this->json(['errors' => [$this->translator->trans('page_text.editor.expired', [], 'admin')]], Response::HTTP_FORBIDDEN);
        }
        $this->texts->reset($key);

        return $this->json(['reset' => true]);
    }

    /**
     * The readable name as one string, e.g. "Unterricht › Preise · Text" (page, then label()).
     */
    private function labelText(string $key): string
    {
        $label = $this->label($key, $this->texts->defaults(), $this->texts->changed());
        $section = $label['section'] ?? (null !== $label['sectionKey'] ? $this->translator->trans('page_text.section.'.$label['sectionKey'], [], 'admin') : null);
        $part = $this->translator->trans('page_text.part.'.$label['part'], [], 'admin');

        return $this->translator->trans('page_text.page.'.explode('.', $key, 2)[0], [], 'admin').' › '.(null !== $section ? $section.' · ' : '').$part;
    }

    /**
     * A readable name for Sonja: the section's heading (as shown on the page) and the kind of text, e.g.
     * "Preise · Text" for lessons.prices.body. A section without heading (home.hero) is named by its key segment,
     * translated in the template (page_text.section.*). Keys without a section (about.body) only get the kind.
     *
     * @param array<string, string>                        $defaults
     * @param array<string, \App\Entity\PageText>         $changed
     *
     * @return array{section: ?string, sectionKey: ?string, part: string}
     */
    private function label(string $key, array $defaults, array $changed): array
    {
        $segments = explode('.', $key);
        $part = array_pop($segments);
        $section = null;
        $sectionKey = null;
        if (\count($segments) > 1) {
            $headingKey = implode('.', $segments).'.heading';
            $heading = isset($changed[$headingKey]) ? $changed[$headingKey]->getBody() : ($defaults[$headingKey] ?? '');
            if ('' !== $heading) {
                $section = $heading;
            } else {
                $sectionKey = end($segments);
            }
        }

        return ['section' => $section, 'sectionKey' => $sectionKey, 'part' => $part];
    }
}
