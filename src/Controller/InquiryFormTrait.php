<?php

declare(strict_types=1);

namespace App\Controller;

use App\Inquiry\Inquiry;
use App\Inquiry\InquiryProcessor;
use App\Inquiry\InquiryStatus;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shared handling of the inquiry forms (contact, booking, lessons) for controllers extending AbstractController.
 *
 * The template includes _partials/inquiry_form.html.twig with the variables form, inquiry_status and inquiry_sent.
 */
trait InquiryFormTrait
{
    /**
     * @template TData of Inquiry
     *
     * @param class-string<FormTypeInterface<TData>> $formType form type and data object must match
     * @param TData                                  $inquiry
     * @param string                                 $type     see App\Inquiry\InquiryProcessor::process()
     */
    private function handleInquiry(
        Request $request,
        InquiryProcessor $inquiries,
        string $formType,
        Inquiry $inquiry,
        string $type,
        string $template,
    ): Response {
        $form = $this->createForm($formType, $inquiry);
        $form->handleRequest($request);

        $status = null;
        if ($form->isSubmitted() && $form->isValid()) {
            $status = $inquiries->process($inquiry, $type, $request);

            // Post/Redirect/Get to the same page; the query parameter shows the confirmation without a session
            if (InquiryStatus::Sent === $status) {
                return $this->redirect($request->getPathInfo().'?gesendet=1#anfrage', Response::HTTP_SEE_OTHER);
            }
        }

        // An invalid form turns the status into 422 (AbstractController::render())
        return $this->render($template, [
            'form' => $form,
            'inquiry_status' => $status,
            'inquiry_sent' => $request->query->has('gesendet'),
        ], new Response(status: $status?->httpStatus() ?? Response::HTTP_OK));
    }
}
