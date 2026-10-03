<?php

namespace App\Data;

use Spatie\LaravelData\Data;

/**
 * Describes a validated pre-update snapshot and the operator guidance stored with it.
 */
final class UpdateSnapshotData extends Data
{
    /**
     * Create a validated snapshot result for update and rollback commands.
     */
    public function __construct(
        public string $path,
        public string $rollbackGuide,
        public string $databaseGuidance,
    ) {}
}
