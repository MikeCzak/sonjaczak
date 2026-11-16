<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Enrollment;
use App\Entity\Enum\CancelledBy;
use App\Entity\Lesson;
use App\Entity\User;
use App\Form\Admin\LessonType;
use App\Lesson\LessonPlanner;
use App\Lesson\MakeUpRule;
use App\Repository\LessonRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A single lesson: the edit page (GET/POST, HTML) and the course grid's endpoint (PATCH, JSON, step C4c).
 * Same address, because the grid cell is the lesson; one route per kind keeps HTML and JSON apart.
 */
#[IsGranted(User::ROLE_ADMIN)]
final class LessonController extends AbstractController
{
    #[Route('/admin/einheiten/{id}', name: 'admin_lesson_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Lesson $lesson, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(LessonType::class, $lesson);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'lesson.flash.saved');

            return $this->redirectToRoute('admin_enrollment_show', ['id' => $lesson->getEnrollment()->getId()]);
        }

        return $this->render('admin/lesson/edit.html.twig', ['lesson' => $lesson, 'form' => $form]);
    }

    /**
     * Changes cancellation and/or note. Only the keys sent are changed (PATCH = change parts):
     * {"cancelledBy": "student"|"teacher"|null, "note": "…"|null}.
     *
     * Withdrawing a cancellation puts the lesson back into its slot. Lessons that were put into that slot in the
     * meantime (e.g. a double lesson of the neighbour) overlap it; they are only deleted with "deleteOverlapping":
     * true, otherwise the answer is 409 with a message for a confirm() popup. Their enrollments are filled up at the
     * end again (LessonPlanner::fillUp(), umsetzung-admin.md C4, question 8, point 4).
     *
     * CSRF: a session token per course (csrf_token('lesson-grid-<course id>') in the grid), sent in the header
     * X-CSRF-Token, because there is no form (erklaerungen/17-csrf.md).
     */
    #[Route('/admin/einheiten/{id}', name: 'admin_lesson_patch', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    public function patch(
        Request $request,
        Lesson $lesson,
        EntityManagerInterface $entityManager,
        LessonRepository $lessons,
        LessonPlanner $planner,
        MakeUpRule $makeUpRule,
        ValidatorInterface $validator,
        TranslatorInterface $translator,
        ClockInterface $clock,
    ): JsonResponse {
        $enrollment = $lesson->getEnrollment();
        $tokenId = 'lesson-grid-'.$enrollment->getCourse()->getId();
        if (!$this->isCsrfTokenValid($tokenId, (string) $request->headers->get('X-CSRF-Token'))) {
            return $this->error($translator, 'grid.error.csrf', Response::HTTP_FORBIDDEN);
        }

        try {
            $data = $request->toArray();
        } catch (\Throwable) {
            return $this->error($translator, 'grid.error.invalid_json', Response::HTTP_BAD_REQUEST);
        }

        $wasCancelled = $lesson->isCancelled();
        if (\array_key_exists('cancelledBy', $data)) {
            $value = $data['cancelledBy'];
            $cancelledBy = \is_string($value) ? CancelledBy::tryFrom($value) : null;
            if (null !== $value && null === $cancelledBy) {
                return $this->error($translator, 'grid.error.invalid_cancelled_by', Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $lesson->setCancelledBy($cancelledBy);
        }
        if (\array_key_exists('note', $data)) {
            if (null !== $data['note'] && !\is_string($data['note'])) {
                return $this->error($translator, 'grid.error.invalid_note', Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $note = trim($data['note'] ?? '');
            $lesson->setNote('' === $note ? null : $note);
        }

        $violations = $validator->validate($lesson);
        if (\count($violations) > 0) {
            return $this->json(['error' => (string) $violations->get(0)->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Keyed by object id: array_unique() would compare entities property by property
        $affected = [spl_object_id($enrollment) => $enrollment];
        $deleted = [];
        $added = 0;
        if ($wasCancelled && !$lesson->isCancelled()) {
            $overlapping = $lessons->findOverlapping($lesson);
            if ([] !== $overlapping && true !== ($data['deleteOverlapping'] ?? false)) {
                // Nothing is flushed: the lesson stays cancelled until Sonja confirms
                return $this->json([
                    'confirm' => $translator->trans('grid.confirm.delete_overlapping', [
                        '%lessons%' => implode("\n", array_map($this->describe(...), $overlapping)),
                    ], 'admin'),
                ], Response::HTTP_CONFLICT);
            }
            $emptied = [];
            foreach ($overlapping as $other) {
                $deleted[] = $other->getId();
                $otherEnrollment = $other->getEnrollment();
                // orphanRemoval: removed from the collection, the row is deleted on flush
                $otherEnrollment->removeLesson($other);
                $affected[spl_object_id($otherEnrollment)] = $otherEnrollment;
                $emptied[spl_object_id($otherEnrollment)] = $otherEnrollment;
            }
            foreach ($emptied as $otherEnrollment) {
                $added += $planner->fillUp($otherEnrollment);
            }
        }
        $entityManager->flush();

        $state = $lesson->state($clock->now());

        return $this->json([
            'lesson' => [
                'id' => $lesson->getId(),
                'cancelledBy' => $lesson->getCancelledBy()?->value,
                'note' => $lesson->getNote(),
                'state' => $state->value,
                'stateLabel' => $translator->trans($state->labelKey(), [], 'admin'),
            ],
            'enrollments' => array_map(fn (Enrollment $each): array => $this->counts($each, $makeUpRule, $translator), array_values($affected)),
            // Lessons deleted or added elsewhere in the grid: the browser reloads the page instead of rebuilding it
            'deleted' => $deleted,
            'added' => $added,
        ]);
    }

    /**
     * The numbers in the column header, already translated, so the browser calculates nothing itself.
     *
     * @return array{id: int|null, count: string, makeUpsDue: int, makeUpLabel: string|null}
     */
    private function counts(Enrollment $enrollment, MakeUpRule $makeUpRule, TranslatorInterface $translator): array
    {
        $due = $makeUpRule->due($enrollment);

        return [
            'id' => $enrollment->getId(),
            'count' => $translator->trans('enrollment.count', [
                '%counted%' => $enrollment->countedLessons(),
                '%planned%' => $enrollment->getCourse()->getPlannedLessons(),
            ], 'admin'),
            'makeUpsDue' => $due,
            'makeUpLabel' => $due > 0 ? $translator->trans('enrollment.make_up_due', ['%count%' => $due], 'admin') : null,
        ];
    }

    private function describe(Lesson $lesson): string
    {
        return \sprintf('%s, %s%s', $lesson->getEnrollment()->getStudent()?->getFullName() ?? '?',
            $lesson->getStartsAt()?->format('d.m. H:i') ?? '', null !== $lesson->getNote() ? ' ('.$lesson->getNote().')' : '');
    }

    private function error(TranslatorInterface $translator, string $key, int $status): JsonResponse
    {
        return $this->json(['error' => $translator->trans($key, [], 'admin')], $status);
    }
}
