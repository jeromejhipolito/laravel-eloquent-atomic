<?php

declare(strict_types=1);

namespace JeromeJHipolito\EloquentAtomic\Traits;

use Illuminate\Database\Eloquent\SoftDeletes;

trait DetectsSoftDeletes
{
    protected static function modelUsesSoftDeletes(string $modelClass): bool
    {
        return in_array(
            SoftDeletes::class,
            class_uses_recursive($modelClass)
        );
    }
}
