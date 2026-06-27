<?php

namespace App\Services\Platform;

use App\Enums\UserRole;
use App\Models\Account;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TenantProvisioner
{
    /**
     * @param  array{
     *     name: string,
     *     slug: string,
     *     domain?: ?string,
     *     timezone: string,
     *     default_currency: string,
     *     default_country: string,
     *     admin_name: string,
     *     admin_email: string,
     *     admin_password: string,
     * }  $data
     * @return array{account: Account, admin: User}
     */
    public function provision(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $account = Account::create([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'domain' => $data['domain'] ?? null,
                'timezone' => $data['timezone'],
                'default_currency' => $data['default_currency'],
                'default_country' => $data['default_country'],
                'settings' => $this->defaultSettings(),
                'is_active' => true,
            ]);

            $admin = User::create([
                'account_id' => $account->id,
                'name' => $data['admin_name'],
                'email' => $data['admin_email'],
                'password' => $data['admin_password'],
                'role' => UserRole::AccountAdmin,
            ]);

            return ['account' => $account, 'admin' => $admin];
        });
    }

    public static function herdLinkCommand(Account $account): string
    {
        return 'herd link '.$account->resolvedDomain();
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultSettings(): array
    {
        return [
            'require_buyer_prepay' => false,
            'validation_integration' => [
                'enabled' => true,
                'email_validation' => true,
                'hlr_validation' => true,
                'quarantine_on_fail' => true,
            ],
        ];
    }
}
