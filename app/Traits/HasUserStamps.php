<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Auto-fill dibuat_oleh dan diedit_oleh dengan user yang sedang login.
 * Syarat: tabel memiliki kolom dibuat_oleh dan diedit_oleh (FK ke users).
 */
trait HasUserStamps
{
    protected static function bootHasUserStamps(): void
    {
        static::creating(function ($model) {
            $model->dibuat_oleh = auth()->id();
            $model->diedit_oleh = auth()->id();
        });

        static::updating(function ($model) {
            $model->diedit_oleh = auth()->id();
        });
    }

    public function dibuatOleh(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'dibuat_oleh');
    }

    public function dieditOleh(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'diedit_oleh');
    }
}