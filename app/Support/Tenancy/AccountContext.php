<?php

namespace App\Support\Tenancy;

use App\Models\Account;

class AccountContext
{
    protected static ?Account $account = null;

    public static function set(?Account $account): void
    {
        static::$account = $account;
    }

    public static function get(): ?Account
    {
        return static::$account;
    }

    public static function id(): ?int
    {
        return static::$account?->id;
    }

    public static function clear(): void
    {
        static::$account = null;
    }
}
