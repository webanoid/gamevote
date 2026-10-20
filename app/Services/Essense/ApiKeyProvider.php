<?php

namespace App\Services\Essense;

use App\Actions\Games\ResetLocalLibrary;
use App\Models\ApiCredential;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Supplies the Essense API key.
 *
 * API_KEY_ESSENCE wins when it is set. Otherwise the app generates its own key on first use
 * and stores it (encrypted) in the database, so a fresh `docker-compose up` needs no setup.
 *
 * Whichever key is active, the provider remembers which library the local votes belong to
 * and resets them when the key changes, because each key's game ids start again at 1.
 */
class ApiKeyProvider
{
    private ?string $resolvedKey = null;

    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $configuredKey,
        private readonly ResetLocalLibrary $resetLocalLibrary,
    ) {}

    /**
     * Whether the app manages its own key, rather than using API_KEY_ESSENCE.
     */
    public function isManaged(): bool
    {
        return blank($this->configuredKey);
    }

    /**
     * Get the key to send with API requests.
     *
     * @throws EssenseApiException
     */
    public function key(): string
    {
        if ($this->resolvedKey !== null) {
            return $this->resolvedKey;
        }

        $key = $this->isManaged() ? $this->storedOrGeneratedKey() : (string) $this->configuredKey;
        $this->adoptLibrary($key);

        return $this->resolvedKey = $key;
    }

    /**
     * Replace a generated key the API rejected. The API's keys reset when its server restarts.
     *
     * @throws EssenseApiException
     */
    public function replace(string $rejectedKey): string
    {
        $key = $this->withLock(function () use ($rejectedKey) {
            $credential = ApiCredential::current();
            $stored = $this->readStoredKey($credential);

            // Another request may already have replaced it while we waited for the lock.
            if ($stored !== null && $stored !== $rejectedKey) {
                return $stored;
            }

            $key = $this->generate();
            $credential->update(['generated_key' => $key]);

            return $key;
        });

        $this->adoptLibrary($key);

        return $this->resolvedKey = $key;
    }

    private function storedOrGeneratedKey(): string
    {
        return $this->readStoredKey(ApiCredential::current()) ?? $this->withLock(function () {
            $credential = ApiCredential::current();

            if (($stored = $this->readStoredKey($credential)) !== null) {
                return $stored;
            }

            $key = $this->generate();
            $credential->update(['generated_key' => $key]);

            return $key;
        });
    }

    /**
     * Reset local votes if they belong to a different key's library, then record this key's.
     */
    private function adoptLibrary(string $key): void
    {
        $fingerprint = hash('sha256', $key);
        $credential = ApiCredential::current();

        if ($credential->library_fingerprint === $fingerprint) {
            return;
        }

        if ($credential->library_fingerprint !== null) {
            $this->resetLocalLibrary->handle();
        }

        $credential->update(['library_fingerprint' => $fingerprint]);
    }

    /**
     * Read the stored key, treating one encrypted under a previous APP_KEY as missing.
     */
    private function readStoredKey(ApiCredential $credential): ?string
    {
        try {
            return filled($credential->generated_key) ? $credential->generated_key : null;
        } catch (DecryptException) {
            return null;
        }
    }

    private function generate(): string
    {
        try {
            $response = Http::baseUrl($this->baseUrl)
                ->acceptJson()
                ->connectTimeout(4)
                ->timeout(8)
                ->post('api-key/generate');
        } catch (ConnectionException $exception) {
            throw new EssenseApiException('The game library could not be reached.', previous: $exception);
        }

        $key = $response->json('key');

        if ($response->failed() || ! is_string($key) || $key === '') {
            throw new EssenseApiException('The game library did not issue an API key.', 'KEY_GENERATION_FAILED');
        }

        return $key;
    }

    /**
     * @template TResult
     *
     * @param  callable(): TResult  $callback
     * @return TResult
     */
    private function withLock(callable $callback): mixed
    {
        try {
            return Cache::lock('essense:api-key', 20)->block(10, $callback);
        } catch (LockTimeoutException $exception) {
            throw new EssenseApiException('Timed out waiting to obtain an API key.', previous: $exception);
        }
    }
}
