<?php

namespace App\Enums;

enum TrelloTaskStatus: string
{
    case Received = 'received';
    case Processing = 'processing';
    case Summarized = 'summarized';
    case Failed = 'failed';
}
