<?php

namespace App\Services\Forms;

use App\Enums\LeadStatus;
use App\Models\HostedForm;
use App\Models\Lead;

class HostedFormSubmissionService
{
    /**
     * @return array<string, mixed>
     */
    public function statusPayload(HostedForm $form, Lead $lead): array
    {
        abort_unless($this->leadBelongsToForm($form, $lead), 404);

        $redirects = app(\App\Services\Leads\LeadRedirectService::class);
        $freshLead = $lead->fresh(['soldToBuyer', 'financials', 'campaign']);
        $status = $freshLead->status->value ?? $freshLead->status;
        $terminal = ! in_array($status, LeadStatus::inFlightValues(), true);

        $redirectUrl = $status === LeadStatus::Sold->value
            ? $redirects->publicRedirectUrl($freshLead)
            : null;

        $declineUrl = in_array($status, ['unsold', 'quarantined'], true)
            ? $redirects->publicDeclineUrl($freshLead)
            : null;

        return [
            'status' => $status,
            'terminal' => $terminal,
            'lead_id' => $freshLead->uuid,
            'queue_id' => $freshLead->queue_id,
            'redirect_url' => $redirectUrl,
            'decline_url' => $declineUrl,
            'reject_reason' => $freshLead->reject_reason,
        ];
    }

    public function leadBelongsToForm(HostedForm $form, Lead $lead): bool
    {
        if ($lead->campaign_id !== $form->campaign_id) {
            return false;
        }

        return $lead->source === 'hosted_form:'.$form->slug;
    }

    /**
     * @return array<string, mixed>
     */
    public function thankYouDefaults(): array
    {
        return [
            'mode' => 'inline',
            'title' => 'Thank you!',
            'message' => 'Your enquiry has been received. We will be in touch shortly.',
            'show_reference' => true,
            'show_submit_another' => true,
            'button_text' => 'Submit another response',
            'confetti' => true,
            'processing_title' => 'Processing your application…',
            'processing_message' => 'Please wait while we match you with a provider. This usually takes a few seconds.',
            'poll_interval_ms' => 1500,
            'poll_max_attempts' => 40,
            'fallback_redirect_url' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function thankYouConfig(HostedForm $form): array
    {
        return array_merge(
            $this->thankYouDefaults(),
            $form->config['thank_you'] ?? [],
        );
    }
}
