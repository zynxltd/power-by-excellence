<?php

namespace App\Services\Api;

use App\Models\ApiKey;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ApiKeyService
{
    public function create(array $data): array
    {
        $secret = Str::random(40);
        $prefix = Str::random(8);

        $apiKey = ApiKey::create([
            'account_id' => $data['account_id'],
            'supplier_id' => $data['supplier_id'] ?? null,
            'name' => $data['name'],
            'type' => $data['type'],
            'key_prefix' => $prefix,
            'key_hash' => Hash::make($secret),
            'permissions' => $data['permissions'] ?? ['leads.create', 'leads.read'],
            'is_active' => true,
        ]);

        return [
            'api_key' => $apiKey,
            'token' => "{$prefix}|{$secret}",
        ];
    }
}
