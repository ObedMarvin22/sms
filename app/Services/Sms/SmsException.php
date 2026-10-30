<?php

namespace App\Services\Sms;

use RuntimeException;

/**
 * The SMS provider refused a message or could not be reached. The message is safe to show staff.
 */
class SmsException extends RuntimeException {}
