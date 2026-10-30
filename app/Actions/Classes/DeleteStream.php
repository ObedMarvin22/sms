<?php

namespace App\Actions\Classes;

use App\Models\Stream;
use Illuminate\Validation\ValidationException;

/**
 * Deletes a stream. A class keeps at least one stream. (From Sprint 3, enrolments block this.)
 */
class DeleteStream
{
    public function handle(Stream $stream): void
    {
        if ($stream->schoolClass->streams()->count() <= 1) {
            throw ValidationException::withMessages(['stream' => 'A class needs at least one stream.']);
        }

        $stream->delete();
    }
}
