<?php

namespace App\Casts;

use App\Support\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class MoneyCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): Money
    {
        return Money::ofCents((int) ($value ?? 0));
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): int
    {
        return $value instanceof Money ? $value->cents() : (int) $value;
    }
}
