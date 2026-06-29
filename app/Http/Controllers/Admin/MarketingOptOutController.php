<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MarketingOptOut;
use App\Services\Messaging\MarketingSuppressionService;
use App\Services\Security\AuditLogService;
use App\Support\Admin\ResolvesAdminAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MarketingOptOutController extends Controller
{
    use ResolvesAdminAccount;

    public function index(Request $request): Response
    {
        $account = $this->resolveAdminAccount($request);

        $query = MarketingOptOut::query()
            ->where('account_id', $account->id)
            ->orderByDesc('created_at');

        if ($fieldType = $request->string('field_type')->toString()) {
            $query->where('field_type', $fieldType);
        }

        if ($source = $request->string('source')->toString()) {
            $query->where('source', $source);
        }

        if ($search = $request->string('q')->toString()) {
            $query->where(function ($builder) use ($search) {
                $builder->where('label', 'like', "%{$search}%")
                    ->orWhere('hash', 'like', "%{$search}%");
            });
        }

        return Inertia::render('Admin/MarketingOptOuts/Index', [
            'optOuts' => $query->paginate(30)->withQueryString(),
            'filters' => [
                'field_type' => $request->input('field_type'),
                'source' => $request->input('source'),
                'q' => $request->input('q'),
            ],
            'sourceOptions' => ['unsubscribe', 'webhook', 'manual', 'import', 'esp', 'esp_bounce', 'esp_complaint'],
            'fieldTypeOptions' => ['email', 'phone1'],
        ]);
    }

    public function import(Request $request, MarketingSuppressionService $suppression): RedirectResponse
    {
        $account = $this->resolveAdminAccount($request);

        $validated = $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $result = $suppression->importCsv($account->id, $validated['file']);

        app(AuditLogService::class)->record(
            'marketing_opt_out.imported',
            'account',
            $account->id,
            $result,
        );

        return back()->with(
            'success',
            "Imported {$result['imported']} suppression(s)".($result['skipped'] > 0 ? "; {$result['skipped']} row(s) skipped." : '.'),
        );
    }
}
