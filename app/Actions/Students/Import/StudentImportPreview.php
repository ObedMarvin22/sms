<?php

namespace App\Actions\Students\Import;

/**
 * The result of a dry run: the checked rows, every error, and what would be created.
 */
final class StudentImportPreview
{
    /**
     * @param  list<array{row_number: int, student: array<string, string>, guardians: list<array<string, string>>}>  $rows
     * @param  list<array{row: int, field: string, message: string}>  $errors
     */
    public function __construct(
        public readonly array $rows,
        public readonly array $errors,
        public readonly int $newGuardians,
        public readonly int $linkedGuardians,
    ) {}

    public function isClean(): bool
    {
        return $this->errors === [] && $this->rows !== [];
    }
}
