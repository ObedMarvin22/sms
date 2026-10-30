<?php

namespace App\Enums;

enum StudentImportStatus: string
{
    case Queued = 'queued';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Importing',
            self::Completed => 'Finished',
        };
    }
}
