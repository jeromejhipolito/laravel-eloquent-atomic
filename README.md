# Laravel Eloquent Atomic

Atomic upsert operations with soft-delete awareness and pessimistic locking for Laravel Eloquent models.

Replaces Laravel's `updateOrCreate()` with a race-condition-safe alternative that uses `SELECT ... FOR UPDATE` with automatic retry on unique constraint violations and deadlocks. Soft-deleted records are automatically detected and restored.

## Installation

```bash
composer require jeromejhipolito/laravel-eloquent-atomic
```

Requires PHP 8.2+ and Laravel 11 or 12.

## Usage

Add the `AtomicUpsert` trait to any service class that needs race-condition-safe upserts:

```php
use JeromeJHipolito\EloquentAtomic\Traits\AtomicUpsert;

class SubscriptionService
{
    use AtomicUpsert;

    public function syncSubscription(array $data): Subscription
    {
        return $this->atomicUpdateOrCreate(
            Subscription::class,
            ['external_id' => $data['id']],       // lookup attributes (must have unique index)
            ['status' => $data['status'], 'name' => $data['name']]  // values to set
        );
    }
}
```

### How It Works

1. Starts a database transaction
2. Attempts `SELECT ... FOR UPDATE` to find an existing record (including soft-deleted)
3. If found: restores (if soft-deleted) and updates in a single query
4. If not found: creates the record
5. On `UniqueConstraintViolationException` or `DeadlockException`: rolls back and retries (up to 3 attempts)

Each retry gets a fresh transaction -- locks are released between attempts to prevent cascading lock waits.

### Requirements

The lookup `$attributes` columns **must** have a UNIQUE constraint at the database level. Without it, the retry mechanism cannot catch race conditions and duplicate records may be created.

```php
Schema::table('subscriptions', function (Blueprint $table) {
    $table->unique('external_id');
});
```

### Soft-Delete Awareness

If the target model uses Laravel's `SoftDeletes` trait, `AtomicUpsert` automatically:

- Queries `withTrashed()` to find soft-deleted records
- Restores and updates in a single query (no intermediate visible state)
- Sets `wasRecentlyCreated = false` on the returned model

No configuration needed -- soft-delete detection is automatic via reflection (cached per model class).

## Configuration

### Custom Primary Key Column

Override `getModelKeyColumn()` if your models use a non-standard primary key:

```php
use JeromeJHipolito\EloquentAtomic\Traits\AtomicUpsert;

class MyService
{
    use AtomicUpsert;

    protected function getModelKeyColumn(): string
    {
        return 'uuid';
    }
}
```

### DetectsSoftDeletes Trait

Use `DetectsSoftDeletes` standalone for soft-delete-aware logic in your own code:

```php
use JeromeJHipolito\EloquentAtomic\Traits\DetectsSoftDeletes;

class MyService
{
    use DetectsSoftDeletes;

    public function findRecord(string $modelClass, int $id)
    {
        $query = $modelClass::query();

        if (static::modelUsesSoftDeletes($modelClass)) {
            $query->withTrashed();
        }

        return $query->find($id);
    }
}
```

## API

### `atomicUpdateOrCreate(string $modelClass, array $attributes, array $values): Model`

| Parameter | Type | Description |
|-----------|------|-------------|
| `$modelClass` | `class-string<T>` | Eloquent model class |
| `$attributes` | `array<string, mixed>` | Lookup columns (must match a unique index) |
| `$values` | `array<string, mixed>` | Columns to create/update |
| **Returns** | `T` | The created or updated model instance |

### `modelUsesSoftDeletes(string $modelClass): bool`

Returns `true` if the model class uses Laravel's `SoftDeletes` trait. Results are cached per class.

### `getModelKeyColumn(): string`

Returns the primary key column name. Defaults to `'id'`. Override for UUID or custom primary keys.

## Database Compatibility

| Feature | MySQL (InnoDB) | PostgreSQL | SQLite |
|---------|---------------|------------|--------|
| `lockForUpdate()` | Full support + gap locks | Full support | Ignored (serialized writes) |
| Deadlock recovery | Supported | Supported | N/A |
| Unique constraint retry | Supported | Supported | Supported |

## License

MIT
