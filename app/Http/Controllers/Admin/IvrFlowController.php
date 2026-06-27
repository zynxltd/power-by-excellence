<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\IvrFlow;
use App\Support\Admin\ResolvesAdminAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IvrFlowController extends Controller
{
    use ResolvesAdminAccount;

    public function index(Request $request): Response
    {
        $flows = IvrFlow::with('campaign:id,name,reference')
            ->orderByDesc('updated_at')
            ->paginate(25);

        return Inertia::render('Admin/CallLogic/Ivr/Index', [
            'flows' => $flows,
            'campaigns' => Campaign::whereIn('channel', ['call', 'hybrid'])
                ->orderBy('name')
                ->get(['id', 'name', 'reference']),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/CallLogic/Ivr/Form', [
            'flow' => null,
            'campaigns' => Campaign::whereIn('channel', ['call', 'hybrid'])
                ->orderBy('name')
                ->get(['id', 'name', 'reference']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $account = $this->resolveAdminAccount($request);

        $validated = $this->validateFlow($request);

        if ($validated['campaign_id'] ?? null) {
            abort_unless(Campaign::find($validated['campaign_id'])?->account_id === $account->id, 403);
        }

        IvrFlow::create(array_merge($validated, ['account_id' => $account->id]));

        return redirect()->route('call-logic.ivr.index')->with('success', 'IVR flow created.');
    }

    public function edit(IvrFlow $ivrFlow): Response
    {
        return Inertia::render('Admin/CallLogic/Ivr/Form', [
            'flow' => $ivrFlow,
            'campaigns' => Campaign::whereIn('channel', ['call', 'hybrid'])
                ->orderBy('name')
                ->get(['id', 'name', 'reference']),
        ]);
    }

    public function update(Request $request, IvrFlow $ivrFlow): RedirectResponse
    {
        $validated = $this->validateFlow($request);
        $ivrFlow->update($validated);

        return redirect()->route('call-logic.ivr.index')->with('success', 'IVR flow updated.');
    }

    public function destroy(IvrFlow $ivrFlow): RedirectResponse
    {
        $ivrFlow->delete();

        return back()->with('success', 'IVR flow deleted.');
    }

    protected function validateFlow(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'campaign_id' => 'nullable|exists:campaigns,id',
            'entry_node' => 'required|string|max:64',
            'nodes' => 'nullable|array',
            'is_active' => 'boolean',
        ]);
    }
}
