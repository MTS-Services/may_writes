<?php

namespace App\Enums;

enum WritingWorkflowStatus: string
{
    case Initialized = 'initialized';
    case InProgress = 'in_progress';
    case Draft = 'draft';
    case Revision = 'revision';
    case Complete = 'complete';
    case Other = 'other';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Initialized => 'Initialized',
            self::InProgress => 'In Progress',
            self::Draft => 'Draft',
            self::Revision => 'Revision',
            self::Complete => 'Complete',
            self::Other => 'Other',
        };
    }
}
