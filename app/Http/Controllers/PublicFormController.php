<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller as BaseController;
use App\Jobs\ProcessLeadJob;
use App\Models\HostedForm;
use App\Models\Lead;
use App\Services\Compliance\FormConsentPolicy;
use App\Services\Forms\HostedFormEmbedService;
use App\Services\Forms\HostedFormSubmissionService;
use App\Services\Leads\LeadIngestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicFormController extends BaseController
{
    public function __construct(
        protected HostedFormEmbedService $embedService,
        protected HostedFormSubmissionService $submissions,
    ) {}

    public function show(Request $request, string $slug): Response
    {
        $form = HostedForm::withoutGlobalScopes()
            ->where('slug', $slug)
            ->live()
            ->firstOrFail();
        $this->embedService->assertEmbedAllowed($form, $request);

        return Inertia::render('Forms/Show', $this->formPageProps($request, $slug));
    }

    public function submit(Request $request, string $slug): Response|RedirectResponse
    {
        $form = HostedForm::withoutGlobalScopes()
            ->where('slug', $slug)
            ->live()
            ->firstOrFail();
        $form->load('campaign');

        $this->embedService->assertSubmitRefererAllowed($form, $request);

        $consentPolicy = FormConsentPolicy::forHostedForm($form);
        FormConsentPolicy::validateSubmission($consentPolicy, $request);

        $tracking = $this->embedService->resolveTracking($form, $request, $request->only(HostedFormEmbedService::TRACKING_QUERY_PARAMS));

        $lead = app(LeadIngestService::class)->ingest([
            'campaign_reference' => $form->campaign->reference,
            'source' => 'hosted_form:'.$form->slug,
            'embed' => $request->boolean('embed') || $request->query('embed') ? '1' : null,
            ...$tracking,
            'consent' => FormConsentPolicy::buildLeadConsentArtifact(
                $consentPolicy,
                $request,
                $request->boolean('consent_accepted'),
            ),
            ...$request->except(['_token', ...HostedFormEmbedService::TRACKING_QUERY_PARAMS, 'embed', 'consent_accepted', 'channel_consent']),
        ]);

        ProcessLeadJob::dispatch($lead->id);

        $thankYou = $this->submissions->thankYouConfig($form);
        $redirectUrl = $form->config['redirect_url'] ?? null;
        $mode = $thankYou['mode'] ?? 'inline';

        if ($redirectUrl && $mode === 'redirect') {
            if ($request->header('X-Inertia')) {
                return Inertia::location($redirectUrl);
            }

            return redirect()->away($redirectUrl);
        }

        return Inertia::render('Forms/Show', array_merge($this->formPageProps($request, $slug), [
            'submitted' => true,
            'submission' => [
                'queue_id' => $lead->queue_id,
                'uuid' => $lead->uuid,
            ],
        ]));
    }

    public function status(string $slug, string $uuid): JsonResponse
    {
        $form = HostedForm::withoutGlobalScopes()
            ->where('slug', $slug)
            ->live()
            ->firstOrFail();

        $lead = Lead::query()
            ->where('uuid', $uuid)
            ->where('campaign_id', $form->campaign_id)
            ->firstOrFail();

        return response()->json($this->submissions->statusPayload($form, $lead));
    }

    /**
     * @return array<string, mixed>
     */
    protected function formPageProps(Request $request, string $slug): array
    {
        $form = HostedForm::withoutGlobalScopes()
            ->where('slug', $slug)
            ->live()
            ->firstOrFail();
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

        $embed = $request->boolean('embed');
        $tracking = $this->embedService->resolveTracking($form, $request);

        return [
            'form' => $form->only(['id', 'name', 'slug']),
            'steps' => $steps,
            'multiStep' => $multiStep,
            'consent' => FormConsentPolicy::forHostedForm($form),
            'submitUrl' => route('forms.submit', $form->slug),
            'statusUrl' => route('forms.status', ['slug' => $form->slug, 'uuid' => '__UUID__']),
            'thankYou' => $this->submissions->thankYouConfig($form),
            'submitted' => false,
            'submission' => null,
            'embed' => $embed,
            'tracking' => $tracking,
            'trackingParams' => HostedFormEmbedService::TRACKING_QUERY_PARAMS,
        ];
    }
}
