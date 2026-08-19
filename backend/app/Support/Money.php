<?php

namespace App\Support;

class Money
{
    public function __construct(private readonly int $cents) {}

    public static function ofCents(int $cents): self
    {
        return new self($cents);
    }

    public static function ofTzs(float $tzs): self
    {
        return new self((int) round($tzs * 100));
    }

    public function cents(): int
    {
        return $this->cents;
    }

    public function tzs(): float
    {
        return $this->cents / 100;
    }

    public function add(self $other): self
    {
        return new self($this->cents + $other->cents);
    }

    public function subtract(self $other): self
    {
        return new self($this->cents - $other->cents);
    }

    public function format(): string
    {
        return 'TZS '.number_format($this->tzs(), 2);
    }

    public function isZero(): bool
    {
        return $this->cents === 0;
    }

    public function isNegative(): bool
    {
        return $this->cents < 0;
    }

    public static function zero(): self
    {
        return new self(0);
    }
}
