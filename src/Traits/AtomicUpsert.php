<?php

declare(strict_types=1);

namespace JeromeJHipolito\EloquentAtomic\Traits;

use Illuminate\Database\DeadlockException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

trait AtomicUpsert
{
    use DetectsSoftDeletes;

    /**
     * @template T of Model
     * @param class-string<T> $modelClass
     * @param array<string, mixed> $attributes
     * @param array<string, mixed> $values
     * @return T
     */
    protected function atomicUpdateOrCreate(string $modelClass, array $attributes, array $values): Model
    {
        $usesSoftDeletes = static::modelUsesSoftDeletes($modelClass);
        $createData = array_merge($attributes, $values);
        $maxRetries = 3;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                return DB::transaction(function () use ($modelClass, $attributes, $createData, $values, $usesSoftDeletes) {
                    $existing = $this->findExistingLocked($modelClass, $attributes, $usesSoftDeletes);

                    if ($existing) {
                        return $this->restoreAndUpdate($existing, $values, $usesSoftDeletes);
                    }

                    return $modelClass::create($createData);
                });
            } catch (UniqueConstraintViolationException|DeadlockException $e) {
                if ($attempt === $maxRetries) {
                    throw $e;
                }
            }
        }
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
            $values[$model->getDeletedAtColumn()] = null;
        }

        $model->update($values);
        $model->wasRecentlyCreated = false;

        return $model;
    }
}
