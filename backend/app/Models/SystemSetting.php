<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['key', 'value'];

    public static function get(string $key, mixed $default = null): mixed
    {
        try {
            $row = static::find($key);

            return $row ? json_decode($row->value, true) : $default;
        } catch (\Throwable) {
            return $default;
        }
    }

    public static function set(string $key, mixed $value): void
    {
        try {
            static::updateOrCreate(
                ['key' => $key],
                ['value' => json_encode($value)]
            );
        } catch (\Throwable) {
            // Table may not exist yet; silently skip
        }
    }
}
