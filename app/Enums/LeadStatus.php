<?php

namespace App\Enums;

enum LeadStatus: string
{
    case Pending = 'pending';
    case Validating = 'validating';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Distributing = 'distributing';
    case Sold = 'sold';
    case Unsold = 'unsold';
    case Quarantined = 'quarantined';
    case Returned = 'returned';
}
