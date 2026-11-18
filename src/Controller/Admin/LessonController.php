<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Enrollment;
use App\Entity\Enum\CancelledBy;
use App\Entity\Lesson;
use App\Entity\User;
use App\Form\Admin\LessonType;
use App\Lesson\LessonDescriber;
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
 * A single lesson: the edit page (GET/POST, HTML) and the course grid's endpoints (JSON, steps C4c/C4d): PATCH
 * changes cancellation and note, DELETE removes the lesson, POST …/doppelstunde adds a second lesson next to it.
 * Same address for page and PATCH/DELETE, because the grid cell is the lesson; one route per kind keeps HTML and
 * JSON apart.
 *
 * CSRF for the JSON endpoints: a session token per course (csrf_token('lesson-grid-<course id>') in the grid), sent
 * in the header X-CSRF-Token, because there is no form (erklaerungen/17-csrf.md).
 */
#[IsGranted(User::ROLE_ADMIN)]
final class LessonController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LessonRepository $lessons,
        private readonly LessonPlanner $planner,
        private readonly MakeUpRule $makeUpRule,
        private readonly TranslatorInterface $translator,
        private readonly ClockInterface $clock,
        private readonly LessonDescriber $describer,
    ) {
    }

    #[Route('/admin/einheiten/{id}', name: 'admin_lesson_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Lesson $lesson): Response
    {
        $form = $this->createForm(LessonType::class, $lesson);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', 'lesson.flash.saved');
            $this->warnAboutBreakDay($lesson);

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
     * true, otherwise the answer is 409 with a message for a confirm() popup. Their enrollments get a lesson at the
     * end again (umsetzung-admin.md C4, question 8, point 4).
     */
    #[Route('/admin/einheiten/{id}', name: 'admin_lesson_patch', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    public function patch(Request $request, Lesson $lesson, ValidatorInterface $validator): JsonResponse
    {
        $data = $this->readJson($request, $lesson);
        if ($data instanceof JsonResponse) {
            return $data;
        }

        $enrollment = $lesson->getEnrollment();
        $wasCancelled = $lesson->isCancelled();
        if (\array_key_exists('cancelledBy', $data)) {
            $value = $data['cancelledBy'];
            $cancelledBy = \is_string($value) ? CancelledBy::tryFrom($value) : null;
            if (null !== $value && null === $cancelledBy) {
                return $this->error('grid.error.invalid_cancelled_by', Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $lesson->setCancelledBy($cancelledBy);
        }
        if (\array_key_exists('note', $data)) {
            if (null !== $data['note'] && !\is_string($data['note'])) {
                return $this->error('grid.error.invalid_note', Response::HTTP_UNPROCESSABLE_ENTITY);
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
        $startsAt = $lesson->getStartsAt();
        $endsAt = $lesson->getEndsAt();
        if ($wasCancelled && !$lesson->isCancelled() && null !== $startsAt && null !== $endsAt) {
            $overlapping = $this->lessons->findOverlapping($enrollment->getCourse(), $startsAt, $endsAt, $lesson);
            if ([] !== $overlapping && true !== ($data['deleteOverlapping'] ?? false)) {
                // Nothing is flushed: the lesson stays cancelled until Sonja confirms
                return $this->json([
                    'confirm' => $this->translator->trans('grid.confirm.delete_overlapping', [
                        '%lessons%' => $this->describer->describe($overlapping),
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
                $added += $this->planner->fillUpAtEnd($otherEnrollment);
            }
        }
        $this->entityManager->flush();

        $state = $lesson->state($this->clock->now());

        return $this->json([
            'lesson' => [
                'id' => $lesson->getId(),
                'cancelledBy' => $lesson->getCancelledBy()?->value,
                'note' => $lesson->getNote(),
                'state' => $state->value,
                'stateLabel' => $this->translator->trans($state->labelKey(), [], 'admin'),
            ],
            'enrollments' => array_map($this->counts(...), array_values($affected)),
            // Lessons deleted or added elsewhere in the grid: the browser reloads the page instead of rebuilding it
            'deleted' => $deleted,
            'added' => $added,
        ]);
    }

    /**
     * Deletes a lesson (entered by mistake, or Sonja does not want to mark it cancelled). The enrollment gets a
     * lesson at the end instead, so it keeps its planned number (umsetzung-admin.md C4, question 10). The browser
     * asks before (the note is lost).
     */
    #[Route('/admin/einheiten/{id}', name: 'admin_lesson_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(Request $request, Lesson $lesson): JsonResponse
    {
        if (!$this->isTokenValid($request, $lesson)) {
            return $this->error('grid.error.csrf', Response::HTTP_FORBIDDEN);
        }

        $enrollment = $lesson->getEnrollment();
        $enrollment->removeLesson($lesson);
        $added = $this->planner->fillUpAtEnd($enrollment);
        $this->entityManager->flush();

        return $this->json(['reload' => true, 'added' => $added]);
    }

    /**
     * Double lesson (umsetzung-admin.md C4, questions 8 and 9): a second lesson of the same enrollment directly
     * before or after this one, typically in the slot of a neighbour who cancelled. JSON {"position": "before"|"after"}.
     *
     * It takes the enrollment's agreed length, not the length of the freed slot: students pay for that length
     * (Mike, question 8 point 6). If it would run into another lesson, nothing is added (409). A double lesson counts
     * as two, so the last future lesson of the enrollment goes, to keep the planned number; if there is none, the
     * enrollment has one lesson more and the answer says so.
     */
    #[Route('/admin/einheiten/{id}/doppelstunde', name: 'admin_lesson_double', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function double(Request $request, Lesson $lesson): JsonResponse
    {
        $data = $this->readJson($request, $lesson);
        if ($data instanceof JsonResponse) {
            return $data;
        }
        $position = $data['position'] ?? null;
        $startsAt = $lesson->getStartsAt();
        $endsAt = $lesson->getEndsAt();
        if (!\in_array($position, ['before', 'after'], true) || null === $startsAt || null === $endsAt || $lesson->isCancelled()) {
            return $this->error('grid.error.invalid_double', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $enrollment = $lesson->getEnrollment();
        $minutes = $enrollment->getLessonMinutes();
        $newStart = 'after' === $position ? $endsAt : $startsAt->modify(\sprintf('-%d minutes', $minutes));
        $newEnd = $newStart->modify(\sprintf('+%d minutes', $minutes));

        $overlapping = $this->lessons->findOverlapping($enrollment->getCourse(), $newStart, $newEnd);
        if ([] !== $overlapping) {
            return $this->json(['error' => $this->translator->trans('grid.error.double_overlaps', [
                '%lessons%' => $this->describer->describe($overlapping),
            ], 'admin')], Response::HTTP_CONFLICT);
        }

        $double = new Lesson($enrollment, $newStart, $minutes);
        $this->entityManager->persist($double);
        $trimmed = $this->planner->removeLastFutureLesson($enrollment, $this->clock->now(), [$double, $lesson]);
        $this->entityManager->flush();

        return $this->json([
            'reload' => true,
            'message' => $trimmed ? null : $this->translator->trans('grid.double.not_trimmed', [], 'admin'),
        ]);
    }

    /**
     * A lesson moved onto a break day stays there (Sonja may agree on a lesson during the holidays), but she is told
     * (umsetzung-admin.md C4f, Mike 2026-11-18). The planner itself never puts lessons there.
     */
    private function warnAboutBreakDay(Lesson $lesson): void
    {
        $startsAt = $lesson->getStartsAt();
        if (null === $startsAt) {
            return;
        }
        foreach ($lesson->getEnrollment()->getCourse()->getBreaks() as $break) {
            if ($break->contains($startsAt)) {
                $this->addFlash('warning', $this->translator->trans('lesson.flash.break_day', [
                    '%date%' => $startsAt->format('d.m.Y'),
                    '%break%' => $break->getLabel(),
                ], 'admin'));

                return;
            }
        }
    }

    /**
     * Checks the token and decodes the body. Returns the data, or the error answer.
     *
     * @return array<mixed>|JsonResponse
     */
    private function readJson(Request $request, Lesson $lesson): array|JsonResponse
    {
        if (!$this->isTokenValid($request, $lesson)) {
            return $this->error('grid.error.csrf', Response::HTTP_FORBIDDEN);
        }

        try {
            return $request->toArray();
        } catch (\Throwable) {
            return $this->error('grid.error.invalid_json', Response::HTTP_BAD_REQUEST);
        }
    }

    private function isTokenValid(Request $request, Lesson $lesson): bool
    {
        $tokenId = 'lesson-grid-'.$lesson->getEnrollment()->getCourse()->getId();

        return $this->isCsrfTokenValid($tokenId, (string) $request->headers->get('X-CSRF-Token'));
    }

    /**
     * The numbers in the column header, already translated, so the browser calculates nothing itself.
     *
     * @return array{id: int|null, count: string, makeUpsDue: int, makeUpLabel: string|null}
     */
    private function counts(Enrollment $enrollment): array
    {
        $due = $this->makeUpRule->due($enrollment);

        return [
            'id' => $enrollment->getId(),
            'count' => $this->translator->trans('enrollment.count', [
                '%counted%' => $enrollment->countedLessons(),
                '%planned%' => $enrollment->getPlannedLessons(),
            ], 'admin'),
            'makeUpsDue' => $due,
            'makeUpLabel' => $due > 0 ? $this->translator->trans('enrollment.make_up_due', ['%count%' => $due], 'admin') : null,
        ];
    }

    private function error(string $key, int $status): JsonResponse
    {
        return $this->json(['error' => $this->translator->trans($key, [], 'admin')], $status);
    }
}
