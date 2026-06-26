<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Mail\PortalCredentialsMail;
use App\Models\Account;
use App\Services\Platform\TenantProvisioner;
use App\Support\Http\ExternalRedirect;
use App\Support\Tenancy\TenantResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class AccountController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeSuperAdmin($request);

        return Inertia::render('Admin/Accounts/Index', [
            'accounts' => Account::withCount(['campaigns', 'leads', 'buyers', 'suppliers'])
                ->orderBy('name')
                ->get()
                ->map(fn (Account $account) => [
                    'id' => $account->id,
                    'name' => $account->brand_name ?: $account->name,
                    'slug' => $account->slug,
                    'domain' => $account->resolvedDomain(),
                    'portal_url' => TenantResolver::portalUrl($account, '/dashboard'),
                    'campaigns_count' => $account->campaigns_count,
                    'leads_count' => $account->leads_count,
                    'buyers_count' => $account->buyers_count,
                    'suppliers_count' => $account->suppliers_count,
                    'admin_user' => $account->users()->whereIn('role', [UserRole::AccountAdmin, UserRole::Staff])->orderBy('id')->first(['id', 'name', 'email']),
                ]),
            'currentAccountId' => session('current_account_id'),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorizeSuperAdmin($request);

        return Inertia::render('Admin/Accounts/Create', [
            'baseDomain' => TenantResolver::baseDomain(),
            'timezones' => timezone_identifiers_list(),
            'currencies' => $this->currencies(),
            'countries' => $this->countries(),
            'reservedSlugs' => $this->reservedSlugs(),
        ]);
    }

    public function store(Request $request, TenantProvisioner $provisioner): RedirectResponse
    {
        $this->authorizeSuperAdmin($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => [
                'required',
                'string',
                'max:63',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                'unique:accounts,slug',
                Rule::notIn($this->reservedSlugs()),
            ],
            'domain' => ['nullable', 'string', 'max:255', 'unique:accounts,domain'],
            'timezone' => 'required|timezone:all',
            'default_country' => ['required', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'],
            'default_currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'admin_name' => 'required|string|max:255',
            'admin_email' => 'required|email|unique:users,email',
            'admin_password' => ['required', Password::defaults()],
            'send_credentials' => 'boolean',
        ], [
            'slug.regex' => 'Slug must be lowercase letters, numbers, and hyphens only.',
            'slug.not_in' => 'That slug is reserved.',
            'default_country.regex' => 'Country must be a 2-letter ISO code (e.g. GB, US).',
            'default_currency.regex' => 'Currency must be a 3-letter ISO code (e.g. GBP, USD).',
        ]);

        $validated['default_country'] = strtoupper($validated['default_country']);
        $validated['default_currency'] = strtoupper($validated['default_currency']);
        $validated['domain'] = filled($validated['domain'] ?? null)
            ? strtolower($validated['domain'])
            : null;

        $result = $provisioner->provision([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'domain' => $validated['domain'],
            'timezone' => $validated['timezone'],
            'default_currency' => $validated['default_currency'],
            'default_country' => $validated['default_country'],
            'admin_name' => $validated['admin_name'],
            'admin_email' => $validated['admin_email'],
            'admin_password' => $validated['admin_password'],
        ]);

        $account = $result['account'];
        $admin = $result['admin'];

        if ($request->boolean('send_credentials')) {
            Mail::to($admin->email)->send(new PortalCredentialsMail(
                $admin,
                $validated['admin_password'],
                TenantResolver::portalUrl($account, '/login'),
                $account->brand_name ?: $account->name
            ));
        }

        $domain = $account->resolvedDomain();
        $message = "Platform «{$account->name}» created at {$domain}.";
        if (app()->environment('local')) {
            $message .= ' Link locally: `'.TenantProvisioner::herdLinkCommand($account).'` or `php artisan platform:link-tenants`.';
        }
        if ($request->boolean('send_credentials')) {
            $message .= ' Login credentials emailed to the admin.';
        }

        return redirect()->route('accounts.index')->with('success', $message);
    }

    public function switch(Request $request): RedirectResponse
    {
        $this->authorizeSuperAdmin($request);

        $validated = $request->validate(['account_id' => 'required|exists:accounts,id']);
        session(['current_account_id' => $validated['account_id']]);

        return back()->with('success', 'Switched partner platform.');
    }

    public function clear(Request $request): RedirectResponse
    {
        $this->authorizeSuperAdmin($request);

        $request->session()->forget(['current_account_id', 'god_mode']);

        if (! TenantResolver::isCentralHost($request->getHost())) {
            return redirect()->away(TenantResolver::centralUrl('/dashboard'))
                ->with('success', 'Returned to central admin — all platforms visible.');
        }

        return back()->with('success', 'Returned to central admin — all platforms visible.');
    }

    public function visit(Request $request, int $accountId): RedirectResponse|HttpResponse
    {
        $this->authorizeSuperAdmin($request);

        $account = Account::findOrFail($accountId);
        session(['current_account_id' => $account->id, 'god_mode' => true]);

        if (TenantResolver::isCentralHost($request->getHost())) {
            $token = Str::random(48);
            Cache::put("god_mode_handoff:{$token}", [
                'super_admin_id' => $request->user()->id,
                'account_id' => $account->id,
            ], now()->addMinutes(2));

            return ExternalRedirect::away(
                $request,
                TenantResolver::portalUrl($account, "/god-mode/handoff/{$token}")
            );
        }

        return redirect()->route('dashboard')->with('success', 'Viewing '.$account->name.' in god mode.');
    }

    protected function authorizeSuperAdmin(Request $request): void
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
    }

    /**
     * @return list<string>
     */
    protected function reservedSlugs(): array
    {
        return ['www', 'api', 'admin', 'app', 'mail', 'help', 'support', 'status', 'horizon'];
    }

    /**
     * @return list<string>
     */
    protected function currencies(): array
    {
        return ['GBP', 'USD', 'EUR', 'AUD', 'CAD', 'NZD', 'ZAR', 'INR', 'AED'];
    }

    /**
     * @return array<string, string>
     */
    protected function countries(): array
    {
        return [
            'GB' => 'United Kingdom',
            'US' => 'United States',
            'CA' => 'Canada',
            'AU' => 'Australia',
            'DE' => 'Germany',
            'FR' => 'France',
            'IE' => 'Ireland',
            'NL' => 'Netherlands',
            'ZA' => 'South Africa',
            'IN' => 'India',
            'AE' => 'United Arab Emirates',
        ];
    }
}
