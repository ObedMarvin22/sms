<?php

namespace App\Actions\Fees;

use App\Models\InstalmentPlan;
use App\Models\InstalmentPlanStep;
use App\Models\Term;
use App\Support\Money\Percent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Creates or changes a term's instalment plan: 1 to 6 steps, each a share of the bill due by a
 * date inside the term, dates in order, shares adding up to exactly 100%. Optionally the term's
 * default plan (replacing the previous default). Every change is logged.
 */
class SaveInstalmentPlan
{
    public const MAX_STEPS = 6;

    /**
     * @param  list<array{due_on?: string, percent?: string}>  $steps
     */
    public function handle(Term $term, ?InstalmentPlan $plan, string $name, array $steps, bool $isDefault): InstalmentPlan
    {
        if ($plan !== null && $plan->term_id !== $term->id) {
            throw ValidationException::withMessages(['name' => 'This plan belongs to another term.']);
        }

        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 100) {
            throw ValidationException::withMessages(['name' => 'Enter a name of up to 100 characters, e.g. Two instalments.']);
        }
        if (InstalmentPlan::where('term_id', $term->id)->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->when($plan, fn ($q) => $q->whereKeyNot($plan->id))->exists()) {
            throw ValidationException::withMessages(['name' => "{$term->name} already has a plan with this name."]);
        }

        $parsed = $this->steps($term, $steps);

        return DB::transaction(function () use ($term, $plan, $name, $parsed, $isDefault): InstalmentPlan {
            if ($isDefault) {
                // Saved as a model (not a query), so dropping the old default is logged.
                $previous = InstalmentPlan::where('term_id', $term->id)->where('is_default', true)
                    ->when($plan, fn ($q) => $q->whereKeyNot($plan->id))->lockForUpdate()->first();
                $previous?->forceFill(['is_default' => false])->save();
            }

            $plan ??= new InstalmentPlan(['term_id' => $term->id]);
            $plan->fill(['name' => $name, 'is_default' => $isDefault])->save();

            $existing = InstalmentPlanStep::where('instalment_plan_id', $plan->id)->orderBy('sequence')->get()->keyBy('sequence');
            foreach ($parsed as $i => [$dueOn, $percent]) {
                $step = $existing->pull($i + 1) ?? new InstalmentPlanStep(['instalment_plan_id' => $plan->id, 'sequence' => $i + 1]);
                $step->fill(['due_on' => $dueOn, 'percent' => $percent])->save();
            }
            foreach ($existing as $leftover) {
                $leftover->delete();
            }

            return $plan->load('steps');
        });
    }

    /**
     * @param  list<array{due_on?: string, percent?: string}>  $steps
     * @return list<array{CarbonImmutable, int}>
     */
    private function steps(Term $term, array $steps): array
    {
        $steps = array_values(array_filter($steps, fn (array $s) => trim((string) ($s['due_on'] ?? '')) !== '' || trim((string) ($s['percent'] ?? '')) !== ''));
        if ($steps === [] || count($steps) > self::MAX_STEPS) {
            throw ValidationException::withMessages(['steps' => 'Add between 1 and '.self::MAX_STEPS.' instalments.']);
        }

        $parsed = [];
        $errors = [];
        $previous = null;
        foreach ($steps as $i => $step) {
            try {
                $dueOn = CarbonImmutable::createFromFormat('!Y-m-d', (string) ($step['due_on'] ?? '')) ?: throw new InvalidArgumentException;
            } catch (InvalidArgumentException) {
                $errors["steps.{$i}.due_on"] = 'Choose a date.';

                continue;
            }
            if ($dueOn->lt($term->starts_on) || $dueOn->gt($term->ends_on)) {
                $errors["steps.{$i}.due_on"] = "Pick a date in {$term->name} ({$term->starts_on->format('j M')} – {$term->ends_on->format('j M Y')}).";
            } elseif ($previous !== null && ! $dueOn->gt($previous)) {
                $errors["steps.{$i}.due_on"] = 'Each instalment must be due after the one before.';
            }

            try {
                $percent = Percent::parse((string) ($step['percent'] ?? ''));
            } catch (InvalidArgumentException) {
                $percent = 0;
            }
            if ($percent <= 0 || $percent > Percent::HUNDRED) {
                $errors["steps.{$i}.percent"] = 'Enter a share between 0.01 and 100.';
            }

            $parsed[] = [$dueOn, $percent];
            $previous = $dueOn;
        }

        if ($errors === [] && ($total = array_sum(array_column($parsed, 1))) !== Percent::HUNDRED) {
            $errors['steps'] = 'The instalments add up to '.Percent::format($total).'; they must add up to 100%.';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $parsed;
    }
}
