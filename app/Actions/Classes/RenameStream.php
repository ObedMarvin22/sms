<?php

namespace App\Actions\Classes;

use App\Models\Stream;

class RenameStream
{
    public function handle(Stream $stream, string $name): Stream
    {
        $stream->update(['name' => ClassRules::streamName($stream->schoolClass, $name, ignore: $stream)]);

        return $stream;
    }
}
