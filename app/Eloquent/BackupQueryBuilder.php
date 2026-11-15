<?php

namespace App\Eloquent;

use Illuminate\Database\Eloquent\Builder;

/**
 * @template TModel of \Illuminate\Database\Eloquent\Model
 *
 * @extends Builder<TModel>
 */
class BackupQueryBuilder extends Builder
{
    public function nonFailed(): self
    {
        $this->where(function (Builder $query) {
            $query
                ->whereNull('completed_at')
                ->orWhere('is_successful', true);
        });

        return $this;
    }

    /**
     * Backups kept after their server was deleted.
     *
     * @return $this
     */
    public function orphaned(): static
    {
        $this->whereNull('server_id');

        return $this;
    }

    /**
     * Backups that finished successfully and can be restored.
     *
     * @return $this
     */
    public function restorable(): static
    {
        $this->whereNotNull('completed_at')->where('is_successful', true);

        return $this;
    }
}
