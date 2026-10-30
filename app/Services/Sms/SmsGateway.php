<?php

namespace App\Services\Sms;

/**
 * Sends one SMS (SPEC section 9). Bound in AppServiceProvider from config('sms.driver').
 */
interface SmsGateway
{
    /**
     * @param  string  $to  +233XXXXXXXXX
     * @param  string  $sender  approved sender ID, up to 11 characters
     * @return string the provider's message id
     *
     * @throws SmsException when the provider refuses or cannot be reached
     */
    public function send(string $to, string $message, string $sender): string;
}
