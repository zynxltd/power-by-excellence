<?php

namespace App\Services\Forms;

use App\Models\Campaign;
use App\Models\HostedForm;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Api\CampaignApiSpecService;
use App\Services\Platform\PlatformNotificationService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class SupplierHostedFormService
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public function __construct(
        protected CampaignApiSpecService $apiSpec,
        protected HostedFormEmbedService $embedService,
    ) {}

    /**
     * @return list<array{id: int, name: string, reference: string}>
     */
    public function campaignsForSupplier(Supplier $supplier): array
    {
        return Campaign::query()
            ->where('account_id', $supplier->account_id)
            ->whereHas('campaignSuppliers', fn ($q) => $q->where('supplier_id', $supplier->id))
            ->orderBy('name')
            ->get(['id', 'name', 'reference'])
            ->map(fn (Campaign $campaign) => $campaign->only(['id', 'name', 'reference']))
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function requestsForSupplier(Supplier $supplier): array
    {
        return HostedForm::withoutGlobalScopes()
            ->where('supplier_id', $supplier->id)
            ->with('campaign:id,name,reference')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (HostedForm $form) => $this->formatRequest($form))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function formatRequest(HostedForm $form): array
    {
        return [
            'id' => $form->id,
            'name' => $form->name,
            'slug' => $form->slug,
            'approval_status' => $form->approval_status,
            'is_live' => $form->isLive(),
            'submitted_at' => $form->submitted_at?->toDateTimeString(),
            'reviewed_at' => $form->reviewed_at?->toDateTimeString(),
            'submission_notes' => $form->submission_notes,
            'rejection_reason' => $form->rejection_reason,
            'campaign' => $form->campaign?->only(['id', 'name', 'reference']),
            'config' => [
                'redirect_url' => $form->config['redirect_url'] ?? '',
                'allowed_domains' => $form->config['allowed_domains'] ?? [],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Supplier $supplier, array $data): HostedForm
    {
        $campaign = $this->resolveCampaignForSupplier($supplier, (int) $data['campaign_id']);
        $source = $supplier->sources()->orderBy('id')->first();

        $form = HostedForm::create([
            'account_id' => $supplier->account_id,
            'campaign_id' => $campaign->id,
            'supplier_id' => $supplier->id,
            'name' => $data['name'],
            'is_active' => false,
            'approval_status' => self::STATUS_DRAFT,
            'config' => $this->buildConfig($supplier, $campaign, $source?->sid, $data),
        ]);

        return $form;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Supplier $supplier, HostedForm $form, array $data): HostedForm
    {
        $this->assertOwnedBySupplier($supplier, $form);
        $this->assertEditableBySupplier($form);

        $campaign = $this->resolveCampaignForSupplier($supplier, (int) $data['campaign_id']);
        $source = $supplier->sources()->orderBy('id')->first();

        $form->update([
            'campaign_id' => $campaign->id,
            'name' => $data['name'],
            'approval_status' => self::STATUS_DRAFT,
            'submitted_at' => null,
            'reviewed_at' => null,
            'reviewed_by_user_id' => null,
            'rejection_reason' => null,
            'config' => $this->buildConfig($supplier, $campaign, $source?->sid, $data),
        ]);

        return $form->fresh();
    }

    public function submitForApproval(Supplier $supplier, HostedForm $form, ?User $actor, ?string $notes = null): HostedForm
    {
        $this->assertOwnedBySupplier($supplier, $form);
        $this->assertEditableBySupplier($form);

        $form->update([
            'approval_status' => self::STATUS_PENDING,
            'submitted_at' => now(),
            'submission_notes' => $notes ?: $form->submission_notes,
            'rejection_reason' => null,
            'reviewed_at' => null,
            'reviewed_by_user_id' => null,
        ]);

        $form->loadMissing(['campaign', 'account', 'supplier']);

        app(PlatformNotificationService::class)->notifyTenantFormApprovalRequest(
            $form->account,
            $actor,
            $form,
        );

        return $form->fresh();
    }

    public function approve(HostedForm $form, User $reviewer): HostedForm
    {
        abort_unless($form->approval_status === self::STATUS_PENDING, 422, 'Only pending forms can be approved.');

        $form->update([
            'approval_status' => self::STATUS_APPROVED,
            'is_active' => true,
            'reviewed_at' => now(),
            'reviewed_by_user_id' => $reviewer->id,
            'rejection_reason' => null,
        ]);

        return $form->fresh();
    }

    public function reject(HostedForm $form, User $reviewer, string $reason): HostedForm
    {
        abort_unless($form->approval_status === self::STATUS_PENDING, 422, 'Only pending forms can be rejected.');

        $form->update([
            'approval_status' => self::STATUS_REJECTED,
            'is_active' => false,
            'reviewed_at' => now(),
            'reviewed_by_user_id' => $reviewer->id,
            'rejection_reason' => $reason,
        ]);

        return $form->fresh();
    }

    /**
     * @return array{pending: int, draft: int, rejected: int}
     */
    public function adminStats(): array
    {
        $query = HostedForm::query()->whereNotNull('supplier_id');

        return [
            'pending' => (clone $query)->where('approval_status', self::STATUS_PENDING)->count(),
            'draft' => (clone $query)->where('approval_status', self::STATUS_DRAFT)->count(),
            'rejected' => (clone $query)->where('approval_status', self::STATUS_REJECTED)->count(),
        ];
    }

    /**
     * @return Collection<int, HostedForm>
     */
    public function pendingForAdmin(): Collection
    {
        return HostedForm::query()
            ->where('approval_status', self::STATUS_PENDING)
            ->with(['campaign:id,name,reference', 'supplier:id,name,reference'])
            ->orderBy('submitted_at')
            ->get();
    }

    protected function resolveCampaignForSupplier(Supplier $supplier, int $campaignId): Campaign
    {
        $campaign = Campaign::query()
            ->where('account_id', $supplier->account_id)
            ->whereKey($campaignId)
            ->whereHas('campaignSuppliers', fn ($q) => $q->where('supplier_id', $supplier->id))
            ->first();

        if (! $campaign) {
            throw ValidationException::withMessages([
                'campaign_id' => 'You are not linked to this campaign.',
            ]);
        }

        return $campaign;
    }

    protected function assertOwnedBySupplier(Supplier $supplier, HostedForm $form): void
    {
        if ($form->supplier_id !== $supplier->id) {
            abort(403, 'This form does not belong to your supplier account.');
        }
    }

    protected function assertEditableBySupplier(HostedForm $form): void
    {
        if (! in_array($form->approval_status, [self::STATUS_DRAFT, self::STATUS_REJECTED], true)) {
            throw ValidationException::withMessages([
                'form' => 'This form cannot be edited while it is pending review or already approved.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function buildConfig(Supplier $supplier, Campaign $campaign, ?string $defaultSid, array $data): array
    {
        $allowedDomains = collect($data['allowed_domains'] ?? [])
            ->map(fn ($domain) => trim((string) $domain))
            ->filter()
            ->values()
            ->all();

        return [
            'multi_step' => true,
            'redirect_url' => $data['redirect_url'] ?? '',
            'allowed_domains' => $allowedDomains,
            'default_supplier_id' => $supplier->id,
            'default_sid' => $defaultSid,
            'embed_height' => 720,
            'steps' => $this->defaultStepsFromCampaign($campaign),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function defaultStepsFromCampaign(Campaign $campaign): array
    {
        $spec = $this->apiSpec->defaultSpec($campaign);
        $fields = collect($spec['fields'] ?? [])
            ->take(8)
            ->map(fn (array $field) => [
                'name' => $field['name'],
                'label' => $field['label'],
                'type' => $field['form_type'] ?? 'text',
                'required' => (bool) ($field['required'] ?? false),
                'options' => $field['enum'] ?? [],
            ])
            ->values()
            ->all();

        if ($fields === []) {
            $fields = [
                ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true, 'options' => []],
            ];
        }

        return [[
            'id' => 'step-1',
            'title' => 'Your details',
            'description' => '',
            'fields' => $fields,
        ]];
    }
}
