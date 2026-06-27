<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VerifyBatch;
use App\Services\Leads\VerifyBatchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VerifyBatchController extends Controller
{
    public function __construct(protected VerifyBatchService $verifyBatch) {}

    public function index(): Response
    {
        return Inertia::render('Admin/VerifyBatches/Index', [
            'batches' => VerifyBatch::with('user:id,name')
                ->orderByDesc('created_at')
                ->paginate(25),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $file = $request->file('file');
        $rows = $this->verifyBatch->parseCsv($file->getRealPath());

        $batch = VerifyBatch::create([
            'user_id' => $request->user()->id,
            'filename' => $file->getClientOriginalName(),
            'status' => 'pending',
            'total_rows' => count($rows),
            'results' => $rows,
        ]);

        return redirect()
            ->route('verify-batches.show', $batch)
            ->with('success', 'Batch uploaded. Process it to validate rows.');
    }

    public function process(VerifyBatch $verifyBatch): RedirectResponse
    {
        if ($verifyBatch->status === 'completed') {
            return back()->with('error', 'Batch already processed.');
        }

        $result = $this->verifyBatch->process($verifyBatch);

        $verifyBatch->update([
            'status' => 'completed',
            'valid_rows' => $result['valid'],
            'invalid_rows' => $result['invalid'],
            'results' => $result['results'],
        ]);

        return back()->with('success', "Batch processed: {$result['valid']} valid, {$result['invalid']} invalid.");
    }

    public function show(VerifyBatch $verifyBatch): Response
    {
        return Inertia::render('Admin/VerifyBatches/Show', [
            'batch' => $verifyBatch->load('user:id,name'),
        ]);
    }
}
