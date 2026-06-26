<?php

namespace App\Services\Validation\Contracts;

use App\Services\Validation\ValidationContext;
use App\Services\Validation\ValidationResult;

interface ValidationProvider
{
    public function validateEmail(?string $email, ?ValidationContext $context = null): ValidationResult;

    public function validateHlr(?string $phone, ?ValidationContext $context = null): ValidationResult;

    public function validateIp(?string $ip, ?ValidationContext $context = null): ValidationResult;

    public function validateUrl(?string $url, ?ValidationContext $context = null): ValidationResult;
}
