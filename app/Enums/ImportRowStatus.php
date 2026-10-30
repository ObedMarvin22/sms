<?php

namespace App\Enums;

enum ImportRowStatus: string
{
    case Pending = 'pending';
    case Imported = 'imported';
    case Failed = 'failed';
}
