<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LandingContent extends Model
{
    protected $fillable = ['content'];

    protected function casts(): array
    {
        return ['content' => 'array'];
    }

    /** Data tersimpan (baris tunggal id=1), array kosong bila belum pernah disimpan. */
    public static function current(): array
    {
        return static::query()->find(1)?->content ?? [];
    }
}
