<?php

namespace App\Services\Paystack;

use RuntimeException;

/**
 * Paystack refused a request or could not be reached. The message is safe to show staff.
 */
class PaystackException extends RuntimeException {}
