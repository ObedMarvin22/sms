<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when code needs a tenant but none has been resolved for this request or job.
 */
class NoCurrentSchool extends RuntimeException
{
    public static function make(): self
    {
        return new self('No current school is set. Resolve the school (IdentifySchool middleware) before using tenant-scoped code.');
    }
}
