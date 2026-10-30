<?php

namespace App\Enums;

enum BillingRunStatus: string
{
    case Queued = 'queued';
    case Completed = 'completed';
}
