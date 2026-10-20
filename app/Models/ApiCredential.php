<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

/**
 * The single row describing which Essense API key the app uses.
 *
 * `generated_key` is a key the app obtained for itself (encrypted at rest). It is null when
 * the key comes from API_KEY_ESSENCE. `library_fingerprint` identifies the key that the local
 * votes and daily actions belong to, so they can be reset when the active key changes.
 *
 * @property int $id
 * @property string|null $generated_key
 * @property string|null $library_fingerprint
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['generated_key', 'library_fingerprint'])]
#[Hidden(['generated_key'])]
class ApiCredential extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'generated_key' => 'encrypted',
        ];
    }

    /**
     * Get the one credential row, creating it if needed.
     */
    public static function current(): self
    {
        return self::query()->firstOrCreate(['id' => 1]);
    }
}
