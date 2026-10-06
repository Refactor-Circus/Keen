<?php

declare(strict_types=1);

namespace JayI\Keen\Domains\Audit\Services;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Model;
use JayI\Foundation\Audit\AuditHooks;

/**
 * Point-in-time copies of models for the audit log, and the diff between two.
 *
 * A snapshot is the model's attributes plus whatever its package adds through
 * `AuditHooks::snapshot()` (related records whose changes belong in its diff).
 *
 * Secrets never leave this class: a redacted field is kept only as a hash, so
 * a change is still detected, and written to the log as `[redacted]`.
 */
final readonly class Snapshots
{
    public const string REDACTED = '[redacted]';

    private const string SECRET = "\0secret:";

    /** @var array<int, string> */
    private const array ALWAYS_REDACTED = ['password', 'remember_token', 'token_hash', 'token', 'client_secret', 'private_key', 'secret'];

    /** @var array<int, string> */
    private const array IGNORED = ['created_at', 'updated_at'];

    public function __construct(
        private Repository $config,
        private AuditHooks $hooks,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function of(Model $model): array
    {
        $values = $this->attributes($model);

        foreach ($this->hooks->snapshotFor($model) as $key => $value) {
            $values[$key] = $this->isSecret($key) ? $this->secret($value) : $this->hashSecrets($this->normalize($value));
        }

        return $values;
    }

    /**
     * Field changes between two snapshots, as `field => [old, new]`.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    public function diff(array $before, array $after): array
    {
        $changes = [];

        foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $key) {
            $old = $before[$key] ?? null;
            $new = $after[$key] ?? null;

            if ($old !== $new) {
                $changes[$key] = [$this->reveal($old), $this->reveal($new)];
            }
        }

        ksort($changes);

        return $changes;
    }

    /**
     * Redact secret keys anywhere in caller-supplied data.
     *
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    public function redact(array $values): array
    {
        foreach ($values as $key => $value) {
            if (is_string($key) && $this->isSecret($key)) {
                $values[$key] = is_array($value) ? array_map(fn (): string => self::REDACTED, $value) : self::REDACTED;
            } elseif (is_array($value)) {
                $values[$key] = $this->redact($value);
            }
        }

        return $values;
    }

    public function isSecret(string $key): bool
    {
        $field = str_contains($key, '.') ? substr($key, (int) strrpos($key, '.') + 1) : $key;

        $secrets = array_merge(
            self::ALWAYS_REDACTED,
            $this->hooks->redacted(),
            array_map('strval', (array) $this->config->get('keen.redact', [])),
        );

        return in_array($field, $secrets, true) || in_array($key, $secrets, true);
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(Model $model): array
    {
        $values = [];

        foreach (array_keys($model->getAttributes()) as $key) {
            if (in_array($key, self::IGNORED, true)) {
                continue;
            }

            $raw = $model->getAttribute($key);

            $values[$key] = match (true) {
                $this->isSecret($key) => $this->secret($raw),
                // Secrets nested in arrays (e.g. encrypted connection config)
                // are tracked by hash too, so changes show without values.
                is_array($raw) => $this->hashSecrets($this->normalize($raw)),
                default => $this->normalize($raw),
            };
        }

        return $values;
    }

    private function secret(mixed $value): ?string
    {
        return $value === null ? null : self::SECRET.hash('sha256', is_scalar($value) ? (string) $value : (string) json_encode($value));
    }

    private function hashSecrets(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $item) {
            $value[$key] = is_string($key) && $this->isSecret($key) && $item !== null
                ? $this->secret($item)
                : $this->hashSecrets($item);
        }

        return $value;
    }

    private function normalize(mixed $value): mixed
    {
        return match (true) {
            $value instanceof BackedEnum => $value->value,
            $value instanceof DateTimeInterface => $value->format(DateTimeInterface::ATOM),
            is_array($value) => array_map(fn (mixed $item): mixed => $this->normalize($item), $value),
            is_object($value) => (string) json_encode($value),
            default => $value,
        };
    }

    private function reveal(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn (mixed $item): mixed => $this->reveal($item), $value);
        }

        return is_string($value) && str_starts_with($value, self::SECRET) ? self::REDACTED : $value;
    }
}
