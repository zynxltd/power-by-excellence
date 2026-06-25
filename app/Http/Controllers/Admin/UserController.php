<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Mail\PortalCredentialsMail;
use App\Models\Buyer;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Tenancy\TenantResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $query = User::with(['buyer', 'supplier', 'account'])
            ->orderBy('name');

        if ($this->shouldHideSuperAdmins($request)) {
            $query->where('role', '!=', UserRole::SuperAdmin);
        }

        $accountId = $request->attributes->get('account')?->id
            ?? $request->session()->get('current_account_id');

        if ($accountId) {
            $query->where(function ($q) use ($accountId) {
                $q->where('account_id', $accountId)
                    ->orWhereHas('buyer', fn ($b) => $b->where('account_id', $accountId))
                    ->orWhereHas('supplier', fn ($s) => $s->where('account_id', $accountId));
            });
        }

        $users = $query->paginate(25);

        $users->getCollection()->transform(fn (User $u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'role' => $u->role->value,
            'is_suspended' => $u->is_suspended,
            'suspended_at' => $u->suspended_at?->toDateTimeString(),
            'allowed_modules' => $u->allowed_modules ?? \App\Support\AdminModules::defaultsForStaff(),
            'buyer' => $u->buyer?->only(['id', 'name', 'reference']),
            'supplier' => $u->supplier?->only(['id', 'name', 'reference']),
        ]);

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'buyers' => Buyer::orderBy('name')->get(['id', 'name', 'reference']),
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name', 'reference']),
            'modules' => \App\Support\AdminModules::all(),
            'portalUrl' => $this->portalUrl($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => ['required', Password::defaults()],
            'role' => ['required', Rule::in(array_column(UserRole::cases(), 'value'))],
            'buyer_id' => 'nullable|exists:buyers,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'allowed_modules' => 'nullable|array',
            'allowed_modules.*' => ['string', Rule::in(\App\Support\AdminModules::keys())],
            'send_credentials' => 'boolean',
        ]);

        $user = User::create([
            'account_id' => $request->user()->account_id ?? session('current_account_id'),
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => $validated['role'],
            'buyer_id' => $validated['buyer_id'] ?? null,
            'supplier_id' => $validated['supplier_id'] ?? null,
            'allowed_modules' => $validated['role'] === UserRole::Staff->value
                ? ($validated['allowed_modules'] ?? \App\Support\AdminModules::defaultsForStaff())
                : null,
            'email_verified_at' => now(),
        ]);

        if ($request->boolean('send_credentials') && in_array($validated['role'], [UserRole::BuyerPortal->value, UserRole::SupplierPortal->value], true)) {
            $this->mailCredentials($request, $user, $validated['password']);
        }

        return back()->with('success', 'User created.'.($request->boolean('send_credentials') ? ' Credentials emailed.' : ''));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'password' => ['nullable', Password::defaults()],
            'role' => ['required', Rule::in(array_column(UserRole::cases(), 'value'))],
            'buyer_id' => 'nullable|exists:buyers,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'allowed_modules' => 'nullable|array',
            'allowed_modules.*' => ['string', Rule::in(\App\Support\AdminModules::keys())],
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'buyer_id' => $validated['buyer_id'] ?? null,
            'supplier_id' => $validated['supplier_id'] ?? null,
            'allowed_modules' => $validated['role'] === UserRole::Staff->value
                ? ($validated['allowed_modules'] ?? \App\Support\AdminModules::defaultsForStaff())
                : null,
            ...($validated['password'] ? ['password' => $validated['password']] : []),
        ]);

        return back()->with('success', 'User updated.');
    }

    public function suspend(User $user): RedirectResponse
    {
        abort_if($user->id === auth()->id(), 403, 'Cannot suspend your own account.');
        abort_if($user->isSuperAdmin(), 403, 'Cannot suspend a super admin.');

        $user->update(['is_suspended' => true, 'suspended_at' => now()]);

        return back()->with('success', "{$user->name} suspended — they cannot sign in.");
    }

    public function activate(User $user): RedirectResponse
    {
        $user->update(['is_suspended' => false, 'suspended_at' => null]);

        return back()->with('success', "{$user->name} reactivated.");
    }

    public function emailCredentials(Request $request, User $user): RedirectResponse
    {
        abort_unless(in_array($user->role, [UserRole::BuyerPortal, UserRole::SupplierPortal], true), 422, 'Credentials email is only for portal users.');

        $password = Str::password(12);
        $user->update(['password' => $password]);
        $this->mailCredentials($request, $user, $password);

        return back()->with('success', "New credentials emailed to {$user->email}.");
    }

    public function destroy(User $user): RedirectResponse
    {
        abort_if($user->id === auth()->id(), 403, 'Cannot delete your own account.');

        $user->delete();

        return back()->with('success', 'User deleted.');
    }

    protected function mailCredentials(Request $request, User $user, string $password): void
    {
        $account = $user->resolveAccount() ?? $request->attributes->get('account');
        $platformName = $account?->brand_name ?: $account?->name ?: 'PowerByExcellence';
        $portalUrl = $account
            ? TenantResolver::portalUrl($account, '/login')
            : url('/login');

        try {
            Mail::to($user->email)->send(new PortalCredentialsMail($user, $password, $portalUrl, $platformName));
        } catch (\Throwable) {
            // Logged mail failures should not block user creation in dev
        }
    }

    protected function portalUrl(Request $request): string
    {
        $account = $request->attributes->get('account') ?? $request->user()?->resolveAccount();

        return $account ? TenantResolver::portalUrl($account, '/login') : url('/login');
    }

    protected function shouldHideSuperAdmins(Request $request): bool
    {
        if (! TenantResolver::isCentralHost($request->getHost())) {
            return true;
        }

        if ($request->session()->has('current_account_id')) {
            return true;
        }

        return (bool) $request->attributes->get('host_account');
    }
}
