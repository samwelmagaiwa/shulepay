<?php

namespace App\Services\Suppliers;

use App\Models\GoodsReceipt;
use App\Models\Supplier;

/** SUP-0001 / GRN-0001 style numbers, counted per school (each school has its own tenant DB or school_id). */
final class SupplierNumbering
{
    public static function supplierCode(): string
    {
        return self::next(Supplier::withTrashed(), 'code', 'SUP-');
    }

    public static function grnNumber(): string
    {
        return self::next(GoodsReceipt::query(), 'grn_number', 'GRN-');
    }

    private static function next($query, string $column, string $prefix): string
    {
        $max = 0;
        foreach ((clone $query)->where($column, 'like', $prefix.'%')->pluck($column) as $value) {
            $max = max($max, (int) substr($value, strlen($prefix)));
        }

        return $prefix.str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }
}
