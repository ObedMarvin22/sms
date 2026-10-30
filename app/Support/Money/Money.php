<?php

namespace App\Support\Money;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * An amount in Ghana cedis, held as whole pesewas (100 pesewas = GH₵1) so sums never drift the
 * way floats do. Immutable: every operation returns a new Money.
 *
 * Stored in integer columns through App\Casts\MoneyCast. JSON (and the activity log) see the
 * pesewa integer.
 */
final readonly class Money implements JsonSerializable, Stringable
{
    private function __construct(public int $pesewas) {}

    public static function pesewas(int $pesewas): self
    {
        return new self($pesewas);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    /**
     * Reads what a person types: "1250", "1,250.5", "GH₵ 1,250.50", "₵80". At most two decimal
     * places; negative amounts are refused (refunds and credits are separate entries).
     *
     * @throws InvalidArgumentException when the text is not an amount
     */
    public static function parse(string $input): self
    {
        $text = str_replace([',', ' ', "\u{00A0}"], '', trim($input));
        $text = (string) preg_replace('/^(GH₵|GHS|GHC|₵)/iu', '', $text);

        if (preg_match('/^(\d{1,10})(?:\.(\d{1,2}))?$/', $text, $m) !== 1) {
            throw new InvalidArgumentException("\"{$input}\" is not an amount in cedis.");
        }

        return new self((int) $m[1] * 100 + (int) str_pad($m[2] ?? '0', 2, '0'));
    }

    /**
     * @param  iterable<self>  $amounts
     */
    public static function sum(iterable $amounts): self
    {
        $total = 0;
        foreach ($amounts as $amount) {
            $total += $amount->pesewas;
        }

        return new self($total);
    }

    public function plus(self $other): self
    {
        return new self($this->pesewas + $other->pesewas);
    }

    public function minus(self $other): self
    {
        return new self($this->pesewas - $other->pesewas);
    }

    public function isZero(): bool
    {
        return $this->pesewas === 0;
    }

    public function isPositive(): bool
    {
        return $this->pesewas > 0;
    }

    public function equals(self $other): bool
    {
        return $this->pesewas === $other->pesewas;
    }

    /**
     * For display: "GH₵1,250.50", "-GH₵20.00".
     */
    public function format(): string
    {
        return ($this->pesewas < 0 ? '-' : '').'GH₵'.$this->plain();
    }

    /**
     * For a form field, without the symbol: "1,250.50".
     */
    public function plain(): string
    {
        $abs = abs($this->pesewas);

        return number_format(intdiv($abs, 100)).'.'.str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);
    }

    public function jsonSerialize(): int
    {
        return $this->pesewas;
    }

    public function __toString(): string
    {
        return $this->format();
    }
}
