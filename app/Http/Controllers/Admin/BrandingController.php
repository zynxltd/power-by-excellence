<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Support\Admin\ResolvesAdminAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class BrandingController extends Controller
{
    use ResolvesAdminAccount;

    public function edit(Request $request): Response
    {
        $account = $this->resolveAdminAccount($request);

        return Inertia::render('Admin/Branding/Edit', [
            'account' => $this->formatAccount($account),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $account = $this->resolveAdminAccount($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'brand_name' => 'nullable|string|max:255',
            'logo' => 'nullable|image|max:2048',
            'favicon' => 'nullable|file|mimes:png,jpg,jpeg,svg,webp,ico|max:512',
            'remove_logo' => 'boolean',
            'remove_favicon' => 'boolean',
        ]);

        if ($request->boolean('remove_logo') && $account->logo_path) {
            Storage::disk('public')->delete($account->logo_path);
            $account->logo_path = null;
        }

        if ($request->hasFile('logo')) {
            if ($account->logo_path) {
                Storage::disk('public')->delete($account->logo_path);
            }
            $account->logo_path = $request->file('logo')->store('logos', 'public');
        }

        if ($request->boolean('remove_favicon') && $account->favicon_path) {
            Storage::disk('public')->delete($account->favicon_path);
            $account->favicon_path = null;
        }

        if ($request->hasFile('favicon')) {
            if ($account->favicon_path) {
                Storage::disk('public')->delete($account->favicon_path);
            }
            $account->favicon_path = $request->file('favicon')->store('favicons', 'public');
        }

        $account->name = $validated['name'];
        $account->brand_name = $validated['brand_name'] ?: null;
        $account->save();

        return back()->with('success', 'Branding updated.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatAccount(Account $account): array
    {
        return [
            'id' => $account->id,
            'name' => $account->name,
            'brand_name' => $account->brand_name,
            'logo_url' => $account->logo_path ? Storage::disk('public')->url($account->logo_path) : null,
            'favicon_url' => $account->favicon_path ? Storage::disk('public')->url($account->favicon_path) : null,
        ];
    }
}
