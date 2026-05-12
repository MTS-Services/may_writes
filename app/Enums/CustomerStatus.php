<?php

namespace App\Enums;

enum CustomerStatus: string
{
    case Active = 'active';
    case Cancelled = 'cancelled';
    case Paused = 'paused';
    case PastDue = 'past_due';
}
