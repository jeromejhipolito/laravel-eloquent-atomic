<?php

declare(strict_types=1);

namespace JeromeJHipolito\EloquentAtomic\Traits;

use Illuminate\Support\Str;

trait HasSlug
{
    use DetectsSoftDeletes;

    public static function bootHasSlug(): void
    {
        static::creating(function ($model) {
            $model->generateSlug();
        });

        static::updating(function ($model) {
            if ($model->isSlugSourceDirty()) {
                $model->generateSlug();
            }
        });
    }

    protected function getSlugSource(): string
    {
        return $this->name ?? '';
    }

    protected function getSlugSourceFields(): array
    {
        return ['name'];
    }

    protected function isSlugSourceDirty(): bool
    {
        return $this->isDirty($this->getSlugSourceFields());
    }

    protected function generateSlug(): void
    {
        $source = $this->getSlugSource();

        if (! empty($source)) {
            $baseSlug = Str::slug($source);
            $slug = $baseSlug;
            $i = 1;

            $usesSoftDeletes = static::modelUsesSoftDeletes(static::class);

            while (
                static::query()
                    ->when($usesSoftDeletes, fn ($q) => $q->withTrashed())
                    ->where('slug', $slug)
                    ->where('id', '!=', $this->id ?? 0)
                    ->exists()
            ) {
                $slug = $baseSlug.'-'.$i++;
            }

            $this->slug = $slug;
        }
    }
}
