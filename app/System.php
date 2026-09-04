<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class System extends Model
{
    protected $guarded = ['id'];

    public static function getProperty($key)
    {
        if (! static::tableReady()) {
            return null;
        }

        $row = static::where('key', $key)->first();

        return $row ? $row->value : null;
    }

    public static function getProperties($keys, $assoc = false)
    {
        if (! static::tableReady()) {
            return $assoc ? [] : collect();
        }

        $rows = static::whereIn('key', (array) $keys)->get();

        return $assoc ? $rows->pluck('value', 'key')->all() : $rows;
    }

    protected static function tableReady(): bool
    {
        try {
            return Schema::hasTable((new static)->getTable());
        } catch (\Throwable $e) {
            return false;
        }
    }
}
