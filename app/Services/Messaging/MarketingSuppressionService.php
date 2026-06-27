<?php

namespace App\Services\Messaging;

use App\Models\Account;
use App\Models\MarketingOptOut;
use App\Services\Leads\FieldHashService;
use Illuminate\Support\Facades\DB;

class MarketingSuppressionService
{
    public function __construct(
        protected FieldHashService $fieldHasher,
    ) {}

    public function isSuppressed(int $accountId, string $channel, string $recipient): bool
    {
        $fieldType = $channel === 'sms' ? 'phone1' : 'email';
        $hash = $this->fieldHasher->resolveHash($fieldType, $recipient);

        if (! $hash) {
            return false;
        }

        if (MarketingOptOut::withoutGlobalScopes()
            ->where('account_id', $accountId)
            ->where('field_type', $fieldType)
            ->where('hash', $hash)
            ->exists()) {
            return true;
        }

        return DB::table('suppression_hashes')
            ->where('account_id', $accountId)
            ->where('field_type', $fieldType)
            ->where('hash', $hash)
            ->exists();
    }

    public function optOut(int $accountId, string $fieldType, string $value, string $source = 'unsubscribe'): void
    {
        $hash = $this->fieldHasher->resolveHash($fieldType, $value);

        if (! $hash) {
            return;
        }

        MarketingOptOut::withoutGlobalScopes()->updateOrCreate(
            [
                'account_id' => $accountId,
                'field_type' => $fieldType,
                'hash' => $hash,
            ],
            ['source' => $source],
        );
    }

    public function optOutFromAccount(Account $account, string $email): void
    {
        $this->optOut($account->id, 'email', $email);
    }
}
