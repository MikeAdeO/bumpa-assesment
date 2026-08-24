<?php

namespace App\Enums;

enum PurchaseStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
