<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller as BaseController;
use App\Jobs\ProcessLeadJob;
use App\Models\HostedForm;
use App\Services\Leads\LeadIngestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicFormController extends BaseController
{
    public function show(string $slug): Response
    {
        return Inertia::render('Forms/Show', $this->formPageProps($slug));
    }

    public function submit(Request $request, string $slug): Response|RedirectResponse
    {
        $form = HostedForm::withoutGlobalScopes()->where('slug', $slug)->where('is_active', true)->firstOrFail();
        $form->load('campaign');

        $allowed = $form->config['allowed_domains'] ?? [];
        if (! empty($allowed)) {
            $origin = parse_url($request->headers->get('referer', ''), PHP_URL_HOST);
            if ($origin && ! in_array($origin, $allowed, true)) {
                abort(403, 'Domain not allowed');
            }
        }

        $lead = app(LeadIngestService::class)->ingest([
            'campaign_reference' => $form->campaign->reference,
            'source' => 'hosted_form:'.$form->slug,
            ...$request->except(['_token']),
        ]);

        ProcessLeadJob::dispatch($lead->id);

        $thankYou = $this->thankYouConfig($form);
        $redirectUrl = $form->config['redirect_url'] ?? null;

        if ($redirectUrl && ($thankYou['mode'] ?? 'inline') === 'redirect') {
            if ($request->header('X-Inertia')) {
                return Inertia::location($redirectUrl);
            }

            return redirect()->away($redirectUrl);
        }

        return Inertia::render('Forms/Show', array_merge($this->formPageProps($slug), [
            'submitted' => true,
            'submission' => [
                'queue_id' => $lead->queue_id,
                'uuid' => $lead->uuid,
            ],
        ]));
    }

    protected function formPageProps(string $slug): array
    {
        $form = HostedForm::withoutGlobalScopes()->where('slug', $slug)->where('is_active', true)->firstOrFail();
        $form->load('campaign.fields');

        $config = $form->config ?? [];
        $multiStep = ! empty($config['multi_step']) && ! empty($config['steps']);
        $steps = $multiStep ? $config['steps'] : [[
            'id' => 'single',
            'title' => $form->name,
            'fields' => $form->campaign->fields->map(fn ($f) => [
                'name' => $f->name,
                'label' => $f->label ?? $f->name,
                'type' => str_contains($f->name, 'email') ? 'email' : (str_contains($f->name, 'phone') ? 'tel' : 'text'),
                'required' => $f->required,
                'options' => $f->validation['enum'] ?? [],
            ])->values()->all(),
        ]];

        return [
            'form' => $form->only(['id', 'name', 'slug']),
            'steps' => $steps,
            'multiStep' => $multiStep,
            'submitUrl' => route('forms.submit', $form->slug),
            'thankYou' => $this->thankYouConfig($form),
            'submitted' => false,
            'submission' => null,
        ];
    }

    protected function thankYouConfig(HostedForm $form): array
    {
        $defaults = [
            'mode' => 'inline',
            'title' => 'Thank you!',
            'message' => 'Your enquiry has been received. We will be in touch shortly.',
            'show_reference' => true,
            'button_text' => 'Submit another response',
            'confetti' => true,
        ];

        return array_merge($defaults, $form->config['thank_you'] ?? []);
    }
}
