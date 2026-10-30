<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Notifications\DatabaseNotification;

/**
 * An in-app notification (Laravel's database channel), owned by one school. Notifiables point
 * their notifications() relation here so new rows get the current school's id.
 */
class Notification extends DatabaseNotification
{
    use BelongsToSchool;
}
