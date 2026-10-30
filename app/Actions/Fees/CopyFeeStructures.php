<?php

namespace App\Actions\Fees;

use App\Models\FeeStructure;
use App\Models\FeeStructureItem;
use App\Models\Term;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Copies every fee structure from one term into another, so a new term starts from last term's
 * fees. Structures the target term already has are left alone; archived items are not copied.
 */
class CopyFeeStructures
{
    /**
     * @return int structures copied
     */
    public function handle(Term $from, Term $to): int
    {
        if ($from->is($to)) {
            throw ValidationException::withMessages(['term' => 'Choose a different term to copy from.']);
        }

        $sources = FeeStructure::with(['lines.feeItem'])->where('term_id', $from->id)->get();
        if ($sources->isEmpty()) {
            throw ValidationException::withMessages(['term' => "{$from->name} has no fee structures to copy."]);
        }

        return DB::transaction(function () use ($sources, $to): int {
            $taken = FeeStructure::where('term_id', $to->id)->get()
                ->map(fn (FeeStructure $s) => $s->school_class_id.'-'.$s->residence->value)->flip();
            $copied = 0;

            foreach ($sources as $source) {
                $lines = $source->lines->filter(fn (FeeStructureItem $line) => $line->feeItem->is_active);
                if ($taken->has($source->school_class_id.'-'.$source->residence->value) || $lines->isEmpty()) {
                    continue;
                }

                $copy = FeeStructure::create([
                    'term_id' => $to->id,
                    'school_class_id' => $source->school_class_id,
                    'residence' => $source->residence,
                ]);
                foreach ($lines as $line) {
                    FeeStructureItem::create(['fee_structure_id' => $copy->id, 'fee_item_id' => $line->fee_item_id, 'amount' => $line->amount]);
                }
                $copied++;
            }

            return $copied;
        });
    }
}
