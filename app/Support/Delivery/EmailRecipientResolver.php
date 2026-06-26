<?php

namespace App\Support\Delivery;

use App\Enums\UserRole;
use App\Models\Buyer;
use App\Models\Delivery;
use App\Models\User;

class EmailRecipientResolver
{
    /**
     * @return array{to: list<string>, cc: list<string>, bcc: list<string>}
     */
    public function resolve(array $config, ?Delivery $delivery = null): array
    {
        $to = [];

        if (! empty($config['use_buyer_email'])) {
            $buyerEmail = $this->buyerEmail($delivery?->buyer);
            if ($buyerEmail) {
                $to[] = $buyerEmail;
            }
        }

        $to = array_merge($to, $this->parseList($config['to'] ?? null));

        return [
            'to' => array_values(array_unique(array_filter($to))),
            'cc' => array_values(array_unique($this->parseList($config['cc'] ?? null))),
            'bcc' => array_values(array_unique($this->parseList($config['bcc'] ?? null))),
        ];
    }

    public function hasRecipients(array $recipients): bool
    {
        return ! empty($recipients['to']);
    }

    /**
     * @param  string|array<int, string>|null  $value
     * @return list<string>
     */
    public function parseList(string|array|null $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map('trim', $value)));
        }

        if (! filled($value)) {
            return [];
        }

        $parts = preg_split('/[,;]+/', (string) $value) ?: [];

        return array_values(array_filter(array_map('trim', $parts)));
    }

    public function buyerEmail(?Buyer $buyer): ?string
    {
        if (! $buyer) {
            return null;
        }

        if (filled($buyer->email)) {
            return trim($buyer->email);
        }

        $portalEmail = User::query()
            ->where('buyer_id', $buyer->id)
            ->where('role', UserRole::BuyerPortal)
            ->value('email');

        return filled($portalEmail) ? trim($portalEmail) : null;
    }
}
