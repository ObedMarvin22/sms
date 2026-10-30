<?php

namespace App\Actions\Classes;

use App\Models\Enrolment;
use App\Models\Stream;
use Illuminate\Validation\ValidationException;

/**
 * Deletes a stream. A class keeps at least one stream; a stream with enrolments is kept.
 */
class DeleteStream
{
    public function handle(Stream $stream): void
    {
        if ($stream->schoolClass->streams()->count() <= 1) {
            throw ValidationException::withMessages(['stream' => 'A class needs at least one stream.']);
        }

        if (Enrolment::where('stream_id', $stream->id)->exists()) {
            throw ValidationException::withMessages(['stream' => 'This stream has student enrolments, so it cannot be deleted.']);
        }

        $stream->delete();
    }
}
