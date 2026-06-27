<?php

namespace App\Services\Leads;

class FieldHashService
{
    public const ALGORITHM = 'sha256';

    /**
     * @var list<string>
     */
    public const PHONE_FIELDS = ['phone1', 'phone2', 'phone3', 'phone'];

    /**
     * Normalise a field value before hashing so equivalent inputs produce the same digest.
     */
    public function normalizeForHash(string $fieldKey, string $value): string
    {
        $value = trim($value);

        if ($this->isEmailField($fieldKey)) {
            return strtolower($value);
        }

        if ($this->isPhoneField($fieldKey)) {
            return $this->normalizePhoneDigits($value);
        }

        return strtolower($value);
    }

    /**
     * Resolve a stored hash from raw or pre-hashed input.
     */
    public function resolveHash(string $fieldKey, string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if ($this->isPreHashed($value)) {
            return strtolower($value);
        }

        return hash(self::ALGORITHM, $this->normalizeForHash($fieldKey, $value));
    }

    public function hash(string $fieldKey, string $value): string
    {
        return hash(self::ALGORITHM, $this->normalizeForHash($fieldKey, $value));
    }

    public function isPreHashed(string $value): bool
    {
        return (bool) preg_match('/^[a-f0-9]{64}$/i', trim($value));
    }

    public function isEmailField(string $fieldKey): bool
    {
        return $fieldKey === 'email';
    }

    public function isPhoneField(string $fieldKey): bool
    {
        return in_array($fieldKey, self::PHONE_FIELDS, true);
    }

    protected function normalizePhoneDigits(string $phone): string
    {
        $trimmed = trim($phone);
        $digits = preg_replace('/\D+/', '', $trimmed) ?? '';

        if ($digits === '') {
            return '';
        }

        if (str_starts_with($trimmed, '0') && ! str_starts_with($trimmed, '00')) {
            $digits = '44'.ltrim($digits, '0');
        }

        return $digits;
    }
}
