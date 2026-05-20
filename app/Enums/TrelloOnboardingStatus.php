<?php

namespace App\Enums;

enum TrelloOnboardingStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
}
