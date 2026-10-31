<?php

namespace App\Actions\Students\Import;

/**
 * The result of a dry run: the checked rows, every error, what would be created, and each row's
 * cells as read (for the error report).
 */
final class StudentImportPreview
{
    /**
     * @param  list<array{row_number: int, student: array<string, string>, guardians: list<array<string, string>>, residence: string, opening_balance: int}>  $rows
     * @param  list<array{row: int, field: string, message: string}>  $errors
     * @param  list<array{row: int, cells: array<string, string>}>  $cells  StudentImportColumns::ALL order
     */
    public function __construct(
        public readonly array $rows,
        public readonly array $errors,
        public readonly int $newGuardians,
        public readonly int $linkedGuardians,
        public readonly array $cells = [],
        public readonly int $boarders = 0,
        public readonly int $balanceCount = 0,
        public readonly int $owedPesewas = 0,
        public readonly int $creditPesewas = 0,
    ) {}

    public function isClean(): bool
    {
        return $this->errors === [] && $this->rows !== [];
    }
}
