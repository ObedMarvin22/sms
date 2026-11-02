<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * A staff change refused because the school is read-only for an unpaid Orange Tree invoice
 * (App\Support\Saas\ReadOnlyGuard). 423 Locked; Livewire screens show it as a message instead.
 */
class SchoolIsReadOnly extends HttpException
{
    public const MESSAGE = 'This school is read-only until the Orange Tree invoice is paid. Nothing was changed.';

    public static function make(string $table): self
    {
        return new self(423, self::MESSAGE.' (refused: '.$table.')');
    }

    public function userMessage(): string
    {
        return self::MESSAGE;
    }
}
