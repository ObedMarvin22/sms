<?php

namespace App\Notifications\Channels;

/**
 * A notification that LogChannel can send: the text a paid channel (SMS) would carry.
 */
interface HasLogMessage
{
    public function toLog(object $notifiable): string;
}
