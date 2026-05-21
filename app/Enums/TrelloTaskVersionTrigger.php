<?php

namespace App\Enums;

enum TrelloTaskVersionTrigger: string
{
    case Created = 'created';
    case Updated = 'updated';
}
