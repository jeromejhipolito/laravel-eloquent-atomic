<?php

declare(strict_types=1);

namespace JeromeJHipolito\EloquentAtomic\Traits;

use Illuminate\Database\Eloquent\SoftDeletes;

trait DetectsSoftDeletes
{
    private static array $softDeletesCache = [];

    protected static function modelUsesSoftDeletes(string $modelClass): bool
    {
        return static::$softDeletesCache[$modelClass] ??= in_array(
            SoftDeletes::class,
            class_uses_recursive($modelClass)
        );
    }

    protected function getModelKeyColumn(): string
    {
        return 'id';
    }
}
