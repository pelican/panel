<?php

namespace App\Casts;

use App\Models\Node;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Normalizes a node's optional Wings overrides on every read and write, so unknown or empty keys
 * are never stored and booleans are always 1/0. A stored `false` would otherwise be cast to an
 * empty string by the form's option fields and dropped the next time the node is saved.
 *
 * This is a cast rather than an accessor so model validation sees the decoded array.
 *
 * @implements CastsAttributes<array<string, mixed>|null, array<string, mixed>|null>
 */
class DaemonConfigCast implements CastsAttributes
{
    /** @param  array<string, mixed>  $attributes */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        $decoded = is_string($value) ? json_decode($value, true) : $value;

        return Node::normalizeDaemonConfig(is_array($decoded) ? $decoded : null);
    }

    /** @param  array<string, mixed>  $attributes */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        $normalized = Node::normalizeDaemonConfig(is_array($value) ? $value : null);

        return $normalized === null ? null : json_encode($normalized, JSON_THROW_ON_ERROR);
    }
}
