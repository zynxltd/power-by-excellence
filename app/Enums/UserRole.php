<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case AccountAdmin = 'account_admin';
    case Staff = 'staff';
    case BuyerPortal = 'buyer_portal';
    case SupplierPortal = 'supplier_portal';
}
