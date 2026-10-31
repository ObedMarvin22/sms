<?php

namespace App\Support\ParentApp;

use App\Enums\AttendanceStatus;
use App\Models\GuardianStudent;
use App\Support\Money\Money;

/**
 * One child as the parent app sees it. The balance is null unless the guardian has full access.
 */
final readonly class ParentChild
{
    public function __construct(
        public GuardianStudent $link,
        public ?AttendanceStatus $today,
        public ?Money $balance,
    ) {}
}
