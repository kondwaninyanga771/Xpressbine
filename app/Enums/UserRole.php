<?php

namespace App\Enums;

enum UserRole: string
{
    case Customer = 'customer';
    case Provider = 'provider';
    case BusinessClient = 'business_client';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Customer => 'Customer',
            self::Provider => 'Service provider',
            self::BusinessClient => 'Business client',
            self::Admin => 'Admin',
        };
    }
}
