<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Services\Api\CampaignApiSpecService;
use App\Services\Api\PlatformApiDocsService;
use App\Support\Admin\ResolvesAdminAccount;
use App\Support\Tenancy\TenantResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApiDocsController extends Controller
{
    use ResolvesAdminAccount;

    public function index(
        Request $request,
        PlatformApiDocsService $docs,
        CampaignApiSpecService $specService,
    ): Response {
        $account = $this->resolveAdminAccount($request);
        $apiBaseUrl = TenantResolver::apiBaseUrl($account);
        $tenantHost = TenantResolver::portalHost($account);

        $campaigns = Campaign::query()
            ->where('account_id', $account->id)
            ->orderBy('name')
            ->get(['id', 'name', 'reference', 'country', 'currency']);

        $selectedCampaign = null;
        $selectedSpec = null;
        $sampleRequest = null;

        if ($campaignId = $request->integer('campaign_id')) {
            $campaign = $campaigns->firstWhere('id', $campaignId)
                ?? Campaign::query()->where('account_id', $account->id)->find($campaignId);

            if ($campaign) {
                $campaign->load('fields');
                $selectedCampaign = $campaign->only(['id', 'name', 'reference', 'country', 'currency']);
                $selectedSpec = $specService->defaultSpec($campaign);
                $sampleRequest = $specService->sampleRequest($campaign, $selectedSpec);
            }
        }

        return Inertia::render('Admin/ApiDocs/Index', [
            'apiBaseUrl' => $apiBaseUrl,
            'tenantHost' => $tenantHost,
            'accountName' => $account->brand_name ?: $account->name,
            'campaigns' => $campaigns,
            'selectedCampaign' => $selectedCampaign,
            'selectedSpec' => $selectedSpec,
            'sampleRequest' => $sampleRequest,
            'sampleResponse' => $specService->sampleResponse(),
            'sampleStatusResponse' => $specService->sampleStatusResponse(),
            'endpoints' => $docs->endpoints(),
            'permissions' => $docs->permissions(),
            'statusFields' => $docs->statusFields(),
            'leadStatuses' => $docs->leadStatuses(),
            'guides' => $docs->guides(),
            'platformGuides' => $docs->platformGuides(),
            'samplePlatformExport' => $docs->samplePlatformExport(),
        ]);
    }
}
