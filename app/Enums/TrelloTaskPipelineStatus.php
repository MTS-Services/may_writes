<?php

namespace App\Enums;

enum TrelloTaskPipelineStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Summarized = 'summarized';
    case Failed = 'failed';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
