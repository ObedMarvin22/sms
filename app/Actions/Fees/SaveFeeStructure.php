<?php

namespace App\Actions\Fees;

use App\Enums\Residence;
use App\Models\FeeItem;
use App\Models\FeeStructure;
use App\Models\FeeStructureItem;
use App\Models\SchoolClass;
use App\Models\Term;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saves what a class pays in a term (day or boarding): one amount per fee item. A blank amount
 * removes that line; leaving every amount blank removes the structure. Each line's change is
 * logged (FeeStructureItem uses LogsActivity).
 */
class SaveFeeStructure
{
    /**
     * @param  array<int|string, string|null>  $amounts  fee item id => amount in cedis ("" = not charged)
     */
    public function handle(Term $term, SchoolClass $class, Residence $residence, array $amounts): ?FeeStructure
    {
        if ($residence === Residence::Boarding) {
            FeeRules::requireBoarding();
        }

        $existing = FeeStructure::with('lines')->where('term_id', $term->id)->where('school_class_id', $class->id)
            ->where('residence', $residence->value)->first();
        $existingLines = $existing?->lines->keyBy('fee_item_id') ?? collect();

        $items = FeeItem::whereIn('id', array_map(intval(...), array_keys($amounts)))->get()->keyBy('id');
        $wanted = [];
        $errors = [];
        foreach ($amounts as $itemId => $input) {
            $itemId = (int) $itemId;
            $item = $items->get($itemId);
            $field = "amounts.{$itemId}";

            if (trim((string) $input) === '') {
                continue;
            }
            if ($item === null) {
                $errors[$field] = 'This fee item does not exist.';

                continue;
            }
            // An archived item may keep its line, but cannot be added to another structure.
            if (! $item->is_active && ! $existingLines->has($itemId)) {
                $errors[$field] = "{$item->name} is archived. Restore it before charging it.";

                continue;
            }
            try {
                $wanted[$itemId] = FeeRules::amount((string) $input, $field);
            } catch (ValidationException $e) {
                $errors += array_map(fn (array $messages) => $messages[0], $e->errors());
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return DB::transaction(function () use ($term, $class, $residence, $existing, $existingLines, $wanted): ?FeeStructure {
            // Deleted one by one (not by query) so each removal is logged.
            foreach ($existingLines as $itemId => $line) {
                if (! array_key_exists($itemId, $wanted)) {
                    $line->delete();
                }
            }

            if ($wanted === []) {
                $existing?->delete();

                return null;
            }

            $structure = $existing ?? FeeStructure::create([
                'term_id' => $term->id,
                'school_class_id' => $class->id,
                'residence' => $residence,
            ]);

            foreach ($wanted as $itemId => $amount) {
                $line = $existingLines->get($itemId) ?? new FeeStructureItem(['fee_structure_id' => $structure->id, 'fee_item_id' => $itemId]);
                $line->amount = $amount;
                $line->save(); // no-op (and no log entry) when the amount is unchanged
            }

            return $structure->load('lines');
        });
    }
}
