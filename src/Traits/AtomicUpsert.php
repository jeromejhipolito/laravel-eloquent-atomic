<?php

declare(strict_types=1);

namespace JeromeJHipolito\EloquentAtomic\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

trait AtomicUpsert
{
    use DetectsSoftDeletes;

    protected function atomicUpdateOrCreate(string $modelClass, array $attributes, array $values): Model
    {
        $usesSoftDeletes = static::modelUsesSoftDeletes($modelClass);

        return DB::transaction(function () use ($modelClass, $attributes, $values, $usesSoftDeletes) {
            $existing = $this->findExistingLocked($modelClass, $attributes, $usesSoftDeletes);

            if ($existing) {
                return $this->restoreAndUpdate($existing, $values, $usesSoftDeletes);
            }

            $maxRetries = 3;
            for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                try {
                    $model = $modelClass::create(array_merge($attributes, $values));
                    $model->wasRecentlyCreated = true;

                    return $model;
                } catch (UniqueConstraintViolationException $e) {
                    $found = $this->findExistingLocked($modelClass, $attributes, $usesSoftDeletes);

                    if ($found) {
                        return $this->restoreAndUpdate($found, $values, $usesSoftDeletes);
                    }

                    if ($attempt === $maxRetries) {
                        throw $e;
                    }
                }
            }
        });
    }

    private function findExistingLocked(string $modelClass, array $attributes, bool $usesSoftDeletes): ?Model
    {
        $query = $modelClass::query();

        if ($usesSoftDeletes) {
            $query->withTrashed();
        }

        foreach ($attributes as $key => $value) {
            $query->where($key, $value);
        }

        return $query->lockForUpdate()->first();
    }

    private function restoreAndUpdate(Model $model, array $values, bool $usesSoftDeletes): Model
    {
        if ($usesSoftDeletes && $model->trashed()) {
            $model->restore();
        }

        $model->update($values);
        $model->wasRecentlyCreated = false;

        return $model;
    }
}
